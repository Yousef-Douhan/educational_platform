<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * قوائم الدورات للإدارة. نطاق visibleTo يقيّد غير المدير بدوراته فقط (نفس الـCMS).
 * العمليات الأخرى تُوجَّه إلى CourseController نفسه.
 */
class CourseListController extends Controller
{
    private const SORTABLE = ['id', 'title_ar', 'title_en', 'price', 'status', 'level', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Course::class);

        $user = $request->user();
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'id';
        $dir  = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->query('per_page', 15), 5), 100);

        $rows = Course::query()
            ->visibleTo($user)
            ->with(['instructor:id,name,email,deleted_at', 'category:id,name_ar,name_en,deleted_at'])
            ->withCount('coInstructors')
            ->search($request->query('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', (string) $request->query('level')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
            ->when($request->filled('instructor_id') && $user->can('courses.manage-all'),
                fn ($q) => $q->where('instructor_id', (int) $request->query('instructor_id')))
            ->when($request->query('pricing') === 'free', fn ($q) => $q->where('price', '<=', 0))
            ->when($request->query('pricing') === 'paid', fn ($q) => $q->where('price', '>', 0))
            ->createdBetween($request->query('from'), $request->query('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }

    public function trashed(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Course::class);

        $rows = Course::onlyTrashed()
            ->visibleTo($request->user())
            ->with(['instructor:id,name,email,deleted_at', 'category:id,name_ar,name_en,deleted_at'])
            ->search($request->query('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 5), 100))
            ->withQueryString();

        return JsonResource::collection($rows)->response();
    }
}
