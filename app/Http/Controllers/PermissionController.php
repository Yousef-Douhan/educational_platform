<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class PermissionController extends Controller
{
    private const SORTABLE = ['id', 'name', 'guard_name', 'created_at', 'updated_at', 'roles_count'];

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . trim($request->get('search')) . '%'))
            ->when($request->filled('guard_name'), fn($q) => $q->where('guard_name', $request->get('guard_name')))
            ->when($request->filled('role_id'), fn($q) => $q->whereHas('roles', fn($r) => $r->where('roles.id', $request->get('role_id'))))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->get('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->get('to')))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.permissions.partials.table', compact('permissions'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.permissions.index', [
            'permissions' => $permissions,
            'stats'       => $this->stats(),
            'guards'      => array_keys(config('auth.guards')),
            'roles'       => Role::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function create(): JsonResponse
    {
        return response()->json(['guards' => array_keys(config('auth.guards'))]);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $permission = Permission::create([
                'name'       => $request->validated('name'),
                'guard_name' => $request->validated('guard_name') ?: config('auth.defaults.guard'),
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('permissions.messages.error')], 500);
        }

        return response()->json([
            'message' => __('permissions.messages.created'),
            'data'    => $permission,
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'data'  => $permission,
            'roles' => $permission->roles()->orderBy('name')->get(['roles.id', 'roles.name']),
        ]);
    }

    public function edit(Permission $permission): JsonResponse
    {
        return response()->json(['data' => $permission->only(['id', 'name', 'guard_name'])]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        try {
            $permission->update(['name' => $request->validated('name')]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('permissions.messages.error')], 500);
        }

        return response()->json([
            'message' => __('permissions.messages.updated'),
            'data'    => $permission->fresh(),
            'stats'   => $this->stats(),
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return response()->json(['message' => __('permissions.messages.in_use')], 409);
        }

        $permission->delete();

        return response()->json([
            'message' => __('permissions.messages.deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        $total = Permission::count();
        $used  = Permission::has('roles')->count();

        return [
            'total'  => $total,
            'used'   => $used,
            'unused' => $total - $used,
            'roles'  => Role::count(),
        ];
    }
}
