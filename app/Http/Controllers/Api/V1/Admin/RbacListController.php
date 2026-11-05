<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** قوائم الأدوار والصلاحيات (ترقيم صفحات). باقي العمليات في RoleController وPermissionController نفسيهما. */
class RbacListController extends Controller
{
    public function roles(Request $request): JsonResponse
    {
        $sort = in_array($request->query('sort'), ['id', 'name', 'guard_name', 'created_at', 'updated_at', 'permissions_count'], true) ? $request->query('sort') : 'id';
        $dir  = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->query('per_page', 15), 5), 100);

        $rows = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.addcslashes(trim((string) $request->query('search')), '%_\\').'%'))
            ->when($request->filled('guard_name'), fn ($q) => $q->where('guard_name', (string) $request->query('guard_name')))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }

    public function permissions(Request $request): JsonResponse
    {
        $sort = in_array($request->query('sort'), ['id', 'name', 'guard_name', 'created_at', 'updated_at', 'roles_count'], true) ? $request->query('sort') : 'id';
        $dir  = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->query('per_page', 15), 5), 100);

        $rows = Permission::query()
            ->withCount('roles')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.addcslashes(trim((string) $request->query('search')), '%_\\').'%'))
            ->when($request->filled('guard_name'), fn ($q) => $q->where('guard_name', (string) $request->query('guard_name')))
            ->when($request->filled('role_id'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', (int) $request->query('role_id'))))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }
}
