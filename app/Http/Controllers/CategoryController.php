<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class CategoryController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'name_ar', 'name_en', 'slug', 'created_at', 'updated_at', 'children_count'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:categories.view',   only: ['index', 'show', 'trashed']),
            new Middleware('permission:categories.create', only: ['create', 'store']),
            new Middleware('permission:categories.edit',   only: ['edit', 'update', 'restore']),
            new Middleware('permission:categories.delete', only: ['destroy', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $categories = Category::query()
            ->with('parent:id,name_ar,name_en,deleted_at')
            ->withCount('children')
            ->search($request->get('search'))
            ->when($request->get('type') === 'root', fn($q) => $q->whereNull('parent_id'))
            ->when($request->get('type') === 'sub', fn($q) => $q->whereNotNull('parent_id'))
            ->when($request->filled('parent_id'), fn($q) => $q->where('parent_id', $request->get('parent_id')))
            ->createdBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.categories.partials.table', compact('categories'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.categories.index', [
            'categories' => $categories,
            'stats'      => $this->stats(),
            'parents'    => $this->parentOptions(),
        ]);
    }

    public function create(): JsonResponse
    {
        return response()->json(['parents' => $this->parentOptions()]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = DB::transaction(function () use ($request) {
                $data         = $request->validated();
                $data['slug'] = filled($data['slug'] ?? null)
                    ? $data['slug']
                    : Category::uniqueSlug($data['name_en']);

                return Category::create($data);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('categories.messages.error')], 500);
        }

        return response()->json([
            'message' => __('categories.messages.created'),
            'data'    => $category->load('parent:id,name_ar,name_en'),
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('parent:id,name_ar,name_en')->loadCount('children');

        return response()->json([
            'data'     => $category,
            'children' => $category->children()->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'icon']),
        ]);
    }

    public function edit(Category $category): JsonResponse
    {
        return response()->json([
            'data'    => $category->only(['id', 'name_ar', 'name_en', 'slug', 'icon', 'parent_id']),
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $category) {
                $data = $request->validated();

                if (blank($data['slug'] ?? null)) {
                    unset($data['slug']); // أبقِ الـ slug الحالي إن تُرك فارغًا
                }

                $category->update($data);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('categories.messages.error')], 500);
        }

        return response()->json([
            'message' => __('categories.messages.updated'),
            'data'    => $category->fresh('parent:id,name_ar,name_en'),
            'stats'   => $this->stats(),
        ]);
    }

    /** حذف مؤقت (Soft Delete) */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists()) {
            return response()->json(['message' => __('categories.messages.has_children')], 409);
        }

        // جديد: لا تُحذف فئة لها دورات فعّالة
        if ($category->courses()->exists()) {
            return response()->json(['message' => __('categories.messages.has_courses')], 409);
        }

        $category->delete();

        return response()->json([
            'message' => __('categories.messages.deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $categories = Category::onlyTrashed()
            ->with('parent:id,name_ar,name_en,deleted_at')       
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.categories.partials.trash-table', compact('categories'))->render(),
            'stats' => $this->stats(),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);

        if ($category->parent_id && Category::onlyTrashed()->whereKey($category->parent_id)->exists()) {
            return response()->json(['message' => __('categories.messages.parent_trashed')], 409);
        }

        $category->restore();

        return response()->json([
            'message' => __('categories.messages.restored'),
            'stats'   => $this->stats(),
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);

        if ($category->children()->withTrashed()->exists()) {
            return response()->json(['message' => __('categories.messages.force_has_children')], 409);
        }

        // جديد: المفتاح الأجنبي courses.category_id يمنع الحذف النهائي، فنردّ برسالة واضحة بدل خطأ 500
        if ($category->courses()->withTrashed()->exists()) {
            return response()->json(['message' => __('categories.messages.force_has_courses')], 409);
        }

        $category->forceDelete();

        return response()->json([
            'message' => __('categories.messages.force_deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        $row = Category::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(parent_id IS NULL) as roots')
            ->selectRaw('SUM(parent_id IS NOT NULL) as subs')
            ->first();

        return [
            'total'         => (int) $row->total,
            'roots'         => (int) $row->roots,
            'subs'          => (int) $row->subs,
            'with_children' => Category::has('children')->count(),
            'trashed'       => Category::onlyTrashed()->count(),
        ];
    }

    /** الفئات المتاحة كأب. عند التعديل نستثني الفئة نفسها وأحفادها */
    private function parentOptions(?Category $except = null)
    {
        $skip = $except ? array_merge([$except->id], $except->descendantIds()) : [];

        return Category::query()
            ->select('id', 'name_ar', 'name_en')
            ->when($skip, fn($q) => $q->whereNotIn('id', $skip))
            ->orderBy('name_en')
            ->get();
    }
}
