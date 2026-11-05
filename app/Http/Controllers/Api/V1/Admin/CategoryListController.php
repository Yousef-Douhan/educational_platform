<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * قوائم التصنيفات للإدارة (ترقيم صفحات). باقي العمليات (show/store/update/destroy/restore/force-delete)
 * تُوجَّه إلى CategoryController نفسه الذي يستخدمه الـCMS، فلا يتكرر أي منطق أو صلاحيات.
 */
class CategoryListController extends Controller
{
    private const SORTABLE = ['id', 'name_ar', 'name_en', 'slug', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'id';
        $dir  = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->query('per_page', 15), 5), 100);

        $rows = Category::query()
            ->with('parent:id,name_ar,name_en,deleted_at')
            ->withCount('children')
            ->search($request->query('search'))
            ->when($request->query('type') === 'root', fn ($q) => $q->whereNull('parent_id'))
            ->when($request->query('type') === 'sub', fn ($q) => $q->whereNotNull('parent_id'))
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', (int) $request->query('parent_id')))
            ->createdBetween($request->query('from'), $request->query('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }

    public function trashed(Request $request): JsonResponse
    {
        $rows = Category::onlyTrashed()
            ->with('parent:id,name_ar,name_en,deleted_at')
            ->search($request->query('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 5), 100))
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }
}
