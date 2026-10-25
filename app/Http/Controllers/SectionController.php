<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionRequest;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SectionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:courses.view', only: ['index']),
            new Middleware('permission:courses.edit', only: ['store', 'update', 'destroy', 'reorder']),
        ];
    }

    /** شجرة المنهج: الأقسام ودروسها المرتبة */
    public function index(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('view', $course);

        $sections = $course->sections()
            ->with(['lessons' => fn($q) => $q->withCount('attachments')])
            ->get();

        $lessons = $sections->flatMap(fn(CourseSection $s) => $s->lessons);

        return response()->json([
            'course'   => $course->only(['id', 'title_ar', 'title_en', 'status']),
            'sections' => $sections->map(fn(CourseSection $s) => [
                'id'       => $s->id,
                'title_ar' => $s->title_ar,
                'title_en' => $s->title_en,
                'lessons'  => $s->lessons->map(fn(Lesson $l) => [
                    'id'                => $l->id,
                    'title_ar'          => $l->title_ar,
                    'title_en'          => $l->title_en,
                    'content_type'      => $l->content_type,
                    'duration_minutes'  => $l->duration_minutes,
                    'is_free_preview'   => $l->is_free_preview,
                    'is_published'      => $l->is_published,
                    'attachments_count' => $l->attachments_count,
                ])->values(),
            ])->values(),
            'totals' => [
                'sections'  => $sections->count(),
                'lessons'   => $lessons->count(),
                'published' => $lessons->where('is_published', true)->count(),
                'minutes'   => (int) $lessons->sum('duration_minutes'),
                'trashed'   => Lesson::onlyTrashed()
                    ->whereHas('section', fn($q) => $q->where('course_id', $course->id))
                    ->count(),
            ],
            'can_edit'         => $request->user()->can('update', $course),
            'can_force_delete' => $request->user()->can('forceDelete', $course),
        ]);
    }

    public function store(SectionRequest $request, Course $course): JsonResponse
    {
        $section = $course->sections()->create([
            ...$request->validated(),
            'sort_order' => (int) CourseSection::where('course_id', $course->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => __('curriculum.messages.section_created'),
            'data'    => $section,
        ], 201);
    }

    public function update(SectionRequest $request, CourseSection $section): JsonResponse
    {
        $section->update($request->validated());

        return response()->json([
            'message' => __('curriculum.messages.section_updated'),
            'data'    => $section,
        ]);
    }

    public function destroy(CourseSection $section): JsonResponse
    {
        $course = $section->course;
        abort_if($course->trashed(), 404);
        Gate::authorize('update', $course);

        // نمنع الحذف حتى لو كانت الدروس محذوفة مؤقتًا، لأن الحذف يزيلها نهائيًا بالتتالي
        if ($section->lessons()->withTrashed()->exists()) {
            return response()->json(['message' => __('curriculum.messages.section_has_lessons')], 409);
        }

        $section->delete();

        return response()->json(['message' => __('curriculum.messages.section_deleted')]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $ids      = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $existing = CourseSection::where('course_id', $course->id)->pluck('id')->all();

        if (count($ids) !== count($existing) || array_diff($ids, $existing)) {
            return response()->json(['message' => __('curriculum.messages.reorder_mismatch')], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                CourseSection::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['message' => __('curriculum.messages.reordered')]);
    }
}
