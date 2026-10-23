<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{
    private const SORTABLE = ['id', 'name', 'guard_name', 'created_at', 'updated_at', 'permissions_count'];

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . trim($request->get('search')) . '%'))
            ->when($request->filled('guard_name'), fn($q) => $q->where('guard_name', $request->get('guard_name')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->get('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->get('to')))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.roles.partials.table', compact('roles'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.roles.index', [
            'roles'  => $roles,
            'stats'  => $this->stats(),
            'guards' => $this->guards(),
        ]);
    }

    public function create(): JsonResponse
    {
        return response()->json([
            'groups' => $this->permissionGroups(),
            'guards' => $this->guards(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = DB::transaction(function () use ($request) {
                $guard = $request->validated('guard_name') ?: config('auth.defaults.guard');

                $role = Role::create([
                    'name'       => $request->validated('name'),
                    'guard_name' => $guard,
                ]);

                $role->syncPermissions($this->permissionsFor($request->validated('permissions', []), $guard));

                return $role;
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('roles.messages.error')], 500);
        }

        return response()->json([
            'message' => __('roles.messages.created'),
            'data'    => $role,
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name,guard_name')->loadCount('users');

        return response()->json([
            'data'   => $role,
            'groups' => $role->permissions
                ->groupBy(fn($p) => $this->groupOf($p->name))
                ->map(fn($items, $group) => ['group' => $group, 'items' => $items->pluck('name')->values()])
                ->values(),
        ]);
    }

    public function edit(Role $role): JsonResponse
    {
        return response()->json([
            'data'           => $role->only(['id', 'name', 'guard_name']),
            'permission_ids' => $role->permissions()->pluck('permissions.id'),
            'groups'         => $this->permissionGroups($role->guard_name),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $role) {
                $role->update(['name' => $request->validated('name')]);
                $role->syncPermissions($this->permissionsFor($request->validated('permissions', []), $role->guard_name));
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('roles.messages.error')], 500);
        }

        return response()->json([
            'message' => __('roles.messages.updated'),
            'data'    => $role->fresh(),
            'stats'   => $this->stats(),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json(['message' => __('roles.messages.in_use')], 409);
        }

        $role->delete(); // يحذف روابط role_has_permissions تلقائيًا

        return response()->json([
            'message' => __('roles.messages.deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        $total = Role::count();
        $with  = Role::has('permissions')->count();

        return [
            'total'               => $total,
            'with_permissions'    => $with,
            'without_permissions' => $total - $with,
            'permissions'         => Permission::count(),
        ];
    }

    private function guards(): array
    {
        return array_keys(config('auth.guards'));
    }

    /** يجمّع الصلاحيات حسب الجزء الذي قبل النقطة (users.create => users) */
    private function permissionGroups(?string $guard = null)
    {
        return Permission::query()
            ->when($guard, fn($q) => $q->where('guard_name', $guard))
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name'])
            ->groupBy(fn($p) => $this->groupOf($p->name))
            ->map(fn($items, $group) => ['group' => $group, 'items' => $items->values()])
            ->values();
    }

    private function groupOf(string $name): string
    {
        return str_contains($name, '.') ? Str::before($name, '.') : 'general';
    }

    private function permissionsFor(array $ids, string $guard)
    {
        return Permission::whereIn('id', $ids)->where('guard_name', $guard)->get();
    }
}
