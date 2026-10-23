<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /*
     * الصلاحيات تُفرض في routes/web.php (can:users.*).
     * هنا فقط قواعد العمل التي لا يكفيها فحص الصلاحية:
     *  - تعيين الأدوار يحتاج users.assign-roles
     *  - غير super-admin لا يرى دور super-admin ولا يعدّل مستخدمًا يملكه
     *  - لا حذف للنفس، ولا حذف/إزالة آخر super-admin
     */

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            $actor = $request->user();
            $actorIsSuper = $this->actorIsSuperAdmin();

            $query = User::query()->with('roles:id,name');

            if ($search = $request->input('search')) {
                // تجميع الشرطين حتى لا يكسر orWhere فلتر الحالة
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 5), 100);
            $users = $query->latest()->paginate($perPage);

            $users->getCollection()->each(function (User $u) use ($actor, $actorIsSuper) {
                $u->setAttribute('is_self', $u->is($actor));
                $u->setAttribute(
                    'is_protected',
                    ! $actorIsSuper && $u->roles->contains('name', $this->superRole())
                );
            });

            return response()->json($users);
        }

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'trashed' => User::onlyTrashed()->count(),
        ];

        $roles = $request->user()->can('users.assign-roles')
            ? $this->assignableRoles()
            : new Collection();

        return view('cms.users.index', compact('stats', 'roles'));
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $roles = $this->canAssignRoles($request)
            ? $this->resolveRoles($this->validatedRoleIds($request))
            : null;

        $user = DB::transaction(function () use ($data, $roles) {
            $user = User::create($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => __('users.created_successfully'),
            'data' => $user->load('roles:id,name'),
        ]);
    }

    public function show(User $user)
    {
        return response()->json($user->load('roles:id,name'));
    }

    public function edit(User $user)
    {
        if ($blocked = $this->blockProtected($user)) {
            return $blocked;
        }

        return response()->json($user->load('roles:id,name'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if ($blocked = $this->blockProtected($user)) {
            return $blocked;
        }

        $data = $request->validated();
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $roles = null;
        if ($this->canAssignRoles($request)) {
            $roles = $this->resolveRoles($this->validatedRoleIds($request));

            // لا يجوز سحب دور super-admin من آخر مستخدم يملكه
            if (
                $user->hasRole($this->superRole())
                && ! $roles->contains('name', $this->superRole())
                && $this->activeSuperAdmins() <= 1
            ) {
                return $this->conflict('users.last_super_admin');
            }
        }

        DB::transaction(function () use ($user, $data, $roles) {
            $user->update($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('users.updated_successfully'),
            'data' => $user->load('roles:id,name'),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return $this->conflict('users.cannot_delete_self');
        }

        if ($blocked = $this->blockProtected($user)) {
            return $blocked;
        }

        if ($user->hasRole($this->superRole()) && $this->activeSuperAdmins() <= 1) {
            return $this->conflict('users.last_super_admin');
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('users.deleted_successfully')
        ]);
    }

    public function trashed(Request $request)
    {
        if ($request->expectsJson()) {
            $users = User::onlyTrashed()->latest()->paginate(10);
            return response()->json($users);
        }

        return view('cms.users.softdelete');
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if ($blocked = $this->blockProtected($user)) {
            return $blocked;
        }

        $user->restore();

        return response()->json([
            'success' => true,
            'message' => __('users.restored_successfully')
        ]);
    }

    public function forceDelete($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if ($blocked = $this->blockProtected($user)) {
            return $blocked;
        }

        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => __('users.force_deleted_successfully')
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function superRole(): string
    {
        return config('cms.super_admin_role');
    }

    private function actorIsSuperAdmin(): bool
    {
        return auth()->user()->hasRole($this->superRole());
    }

    private function activeSuperAdmins(): int
    {
        // User::role() يستثني المحذوفين مؤقتًا تلقائيًا (SoftDeletes)
        return User::role($this->superRole())->count();
    }

    private function canAssignRoles(Request $request): bool
    {
        return $request->user()->can('users.assign-roles');
    }

    /** الأدوار التي يحق للمستخدم الحالي إسنادها (بدون super-admin لغير super-admin). */
    private function assignableRoles()
    {
        return Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->when(! $this->actorIsSuperAdmin(), fn($q) => $q->where('name', '!=', $this->superRole()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** يتحقق من roles[] ويقبل فقط الأدوار المسموح إسنادها. */
    private function validatedRoleIds(Request $request): array
    {
        $assignable = $this->assignableRoles()->pluck('id')->all();

        $validated = $request->validate([
            'roles'   => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::in($assignable)],
        ]);

        return $validated['roles'] ?? [];
    }

    private function resolveRoles(array $ids)
    {
        return Role::whereIn('id', $ids)
            ->where('guard_name', config('auth.defaults.guard'))
            ->get();
    }

    /** غير super-admin لا يعدّل ولا يحذف ولا يستعيد مستخدمًا يملك super-admin. */
    private function blockProtected(User $target): ?JsonResponse
    {
        if (! $this->actorIsSuperAdmin() && $target->hasRole($this->superRole())) {
            return $this->conflict('users.super_admin_protected');
        }

        return null;
    }

    private function conflict(string $messageKey): JsonResponse
    {
        return response()->json(['message' => __($messageKey)], 409);
    }
}
