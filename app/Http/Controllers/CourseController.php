<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class CourseController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'title_ar', 'title_en', 'price', 'status', 'level', 'created_at', 'updated_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:courses.view',   only: ['index', 'show', 'trashed']),
            new Middleware('permission:courses.create', only: ['create', 'store']),
            new Middleware('permission:courses.edit',   only: ['edit', 'update']),
            new Middleware('permission:courses.delete', only: ['destroy', 'restore', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Course::class);

        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $courses = Course::query()
            ->visibleTo($user)
            ->with(['instructor:id,name,email', 'category:id,name_ar,name_en'])
            ->withCount('coInstructors')
            ->search($request->get('search'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('level'), fn($q) => $q->where('level', $request->get('level')))
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->get('category_id')))
            ->when(
                $request->filled('instructor_id') && $user->can('courses.manage-all'),
                fn($q) => $q->where('instructor_id', $request->get('instructor_id'))
            )
            ->when($request->get('pricing') === 'free', fn($q) => $q->where('price', '<=', 0))
            ->when($request->get('pricing') === 'paid', fn($q) => $q->where('price', '>', 0))
            ->createdBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.courses.partials.table', compact('courses'))->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.courses.index', [
            'courses' => $courses,
            'stats'   => $this->stats($user),
            'options' => $this->options($user),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        Gate::authorize('create', Course::class);

        return response()->json(['options' => $this->options($request->user())]);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $path = null;

        try {
            $course = DB::transaction(function () use ($request, &$path) {
                $data = $this->payload($request);

                if ($request->hasFile('image')) {
                    $path = $data['image_url'] = $request->file('image')->store('courses', 'public');
                }

                $course = Course::create($data);
                $this->syncChildren($course, $request);

                return $course;
            });
        } catch (Throwable $e) {
            report($e);
            $this->deleteImage($path);

            return response()->json(['message' => __('courses.messages.error')], 500);
        }

        return response()->json([
            'message' => __('courses.messages.created'),
            'data'    => $course->load('instructor:id,name,email', 'category:id,name_ar,name_en'),
            'stats'   => $this->stats($request->user()),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        Gate::authorize('view', $course);

        $course->load([
            'instructor:id,name,email',
            'category:id,name_ar,name_en',
            'coInstructors:id,name',
            'requirements',
            'learningOutcomes',
        ]);

        return response()->json(['data' => $course]);
    }

    public function edit(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        return response()->json([
            'data'               => $course->load(['requirements', 'learningOutcomes']),
            'co_instructor_ids'  => $course->coInstructors()->pluck('users.id'),
            'options'            => $this->options($request->user()),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $newPath = null;
        $oldPath = $course->image_url;

        try {
            DB::transaction(function () use ($request, $course, &$newPath) {
                $data = $this->payload($request, $course);

                if ($request->hasFile('image')) {
                    $newPath = $data['image_url'] = $request->file('image')->store('courses', 'public');
                } elseif ($request->boolean('remove_image')) {
                    $data['image_url'] = null;
                }

                $course->update($data);
                $this->syncChildren($course, $request);
            });
        } catch (Throwable $e) {
            report($e);
            $this->deleteImage($newPath);

            return response()->json(['message' => __('courses.messages.error')], 500);
        }

        if ($newPath || $request->boolean('remove_image')) {
            $this->deleteImage($oldPath); // نحذف القديمة بعد نجاح الحفظ فقط
        }

        return response()->json([
            'message' => __('courses.messages.updated'),
            'data'    => $course->fresh(['instructor:id,name,email', 'category:id,name_ar,name_en']),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /** حذف مؤقت (Soft Delete) */
    public function destroy(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('delete', $course);

        $course->delete();

        return response()->json([
            'message' => __('courses.messages.deleted'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Course::class);

        $courses = Course::onlyTrashed()
            ->visibleTo($request->user())
            ->with(['instructor:id,name,email', 'category:id,name_ar,name_en'])
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.courses.partials.trash-table', compact('courses'))->render(),
            'stats' => $this->stats($request->user()),
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $course = Course::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $course);

        if ($course->category?->trashed()) {
            return response()->json(['message' => __('courses.messages.category_trashed')], 409);
        }

        if ($course->instructor?->trashed()) {
            return response()->json(['message' => __('courses.messages.instructor_trashed')], 409);
        }

        $course->restore();

        return response()->json([
            'message' => __('courses.messages.restored'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $course = Course::onlyTrashed()->findOrFail($id);
        Gate::authorize('forceDelete', $course);

        if ($this->hasSales($course->id)) {
            return response()->json(['message' => __('courses.messages.has_sales')], 409);
        }

        DB::transaction(function () use ($course) {
            $this->deleteImage($course->image_url);
            $course->forceDelete(); // الجداول الفرعية تُحذف بالتتالي
        });

        return response()->json([
            'message' => __('courses.messages.force_deleted'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /* ---------- Helpers ---------- */

    private function payload(Request $request, ?Course $course = null): array
    {
        $user = $request->user();
        $data = $request->safe()->only([
            'instructor_id',
            'category_id',
            'title_ar',
            'title_en',
            'slug',
            'description_ar',
            'description_en',
            'price',
            'discount_price',
            'level',
            'status',
        ]);

        // غير المدير لا يختار المدرّس: يبقى كما هو، أو يكون هو نفسه عند الإنشاء
        if (! $user->can('courses.manage-all')) {
            $data['instructor_id'] = $course?->instructor_id ?? $user->id;
        }

        if (blank($data['slug'] ?? null)) {
            if ($course) {
                unset($data['slug']);
            } else {
                $data['slug'] = Course::uniqueSlug($data['title_en']);
            }
        }

        $data['price'] = $data['price'] ?? 0;

        // غير الناشر لا يغيّر حالة دورة منشورة أو مؤرشفة
        if ($course && ! $user->can('courses.publish') && ! in_array($course->status, ['draft', 'pending_review'], true)) {
            unset($data['status']);
        }

        return $data;
    }

    private function syncChildren(Course $course, Request $request): void
    {
        $coInstructors = collect($request->validated('co_instructors', []))
            ->map(fn($id) => (int) $id)
            ->reject(fn($id) => $id === $course->instructor_id)
            ->unique()
            ->values()
            ->all();

        $course->coInstructors()->sync($coInstructors);

        $course->requirements()->delete();
        $course->requirements()->createMany(
            collect($request->validated('requirements', []))
                ->map(fn($row) => Arr::only($row, ['requirement_ar', 'requirement_en']))->all()
        );

        $course->learningOutcomes()->delete();
        $course->learningOutcomes()->createMany(
            collect($request->validated('learning_outcomes', []))
                ->map(fn($row) => Arr::only($row, ['outcome_ar', 'outcome_en']))->all()
        );
    }

    private function options(User $user): array
    {
        return [
            'categories' => Category::query()->select('id', 'name_ar', 'name_en')->orderBy('name_en')->get(),
            'instructors' => User::query()
                ->whereHas('roles', fn($q) => $q->where('name', 'instructor'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'levels'              => Course::LEVELS,
            'statuses'            => $user->can('courses.publish') ? Course::STATUSES : ['draft', 'pending_review'],
            'can_pick_instructor' => $user->can('courses.manage-all'),
        ];
    }

    private function stats(User $user): array
    {
        $row = Course::query()
            ->visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'published') as published")
            ->selectRaw("SUM(status IN ('draft', 'pending_review')) as drafts")
            ->selectRaw('SUM(price <= 0) as free')
            ->first();

        return [
            'total'     => (int) $row->total,
            'published' => (int) $row->published,
            'drafts'    => (int) $row->drafts,
            'free'      => (int) $row->free,
            'trashed'   => Course::onlyTrashed()->visibleTo($user)->count(),
        ];
    }

    /** منع الحذف النهائي لدورة لها تسجيلات أو مبيعات (يتجاهل الجداول غير المنشأة بعد) */
    private function hasSales(int $courseId): bool
    {
        foreach (['enrollments', 'order_items', 'certificates'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('course_id', $courseId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
