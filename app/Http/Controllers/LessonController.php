<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:courses.view',   only: ['show', 'trashed']),
            new Middleware('permission:courses.edit',   only: ['store', 'update', 'destroy', 'reorder', 'restore']),
            new Middleware('permission:courses.delete', only: ['forceDelete']),
        ];
    }

    public function store(LessonRequest $request, CourseSection $section): JsonResponse
    {
        $lesson = Lesson::create([
            ...$this->normalize($request->validated()),
            'section_id' => $section->id,
            'sort_order' => $this->nextOrder($section->id),
        ]);

        return response()->json([
            'message' => __('curriculum.messages.lesson_created'),
            'data'    => $lesson,
        ], 201);
    }

    /** بيانات الدرس للتعديل والعرض، مع مرفقاته وأقسام الدورة (للنقل) */
    public function show(Lesson $lesson): JsonResponse
    {
        $course = $lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('view', $course);

        $lesson->load('attachments');

        return response()->json([
            'data'        => $lesson,
            'attachments' => $lesson->attachments->map(fn($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'file_size'    => $a->file_size,
                'download_url' => route('cms.admin.attachments.download', $a),
            ])->values(),
            'sections' => $course->sections()->get(['id', 'title_ar', 'title_en']),
            'options'  => [
                'content_types'   => Lesson::CONTENT_TYPES,
                'video_providers' => Lesson::VIDEO_PROVIDERS,
            ],
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): JsonResponse
    {
        $data = $this->normalize($request->validated());

        if (isset($data['section_id']) && (int) $data['section_id'] !== $lesson->section_id) {
            $data['sort_order'] = $this->nextOrder((int) $data['section_id']); // ينتقل إلى آخر القسم الجديد
        } else {
            unset($data['section_id']);
        }

        $lesson->update($data);

        return response()->json([
            'message' => __('curriculum.messages.lesson_updated'),
            'data'    => $lesson->fresh(),
        ]);
    }

    /** حذف مؤقت */
    public function destroy(Lesson $lesson): JsonResponse
    {
        $course = $lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('update', $course);

        $lesson->delete();

        return response()->json(['message' => __('curriculum.messages.lesson_deleted')]);
    }

    public function reorder(Request $request, CourseSection $section): JsonResponse
    {
        $course = $section->course;
        abort_if($course->trashed(), 404);
        Gate::authorize('update', $course);

        $ids      = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $existing = Lesson::where('section_id', $section->id)->pluck('id')->all();

        if (count($ids) !== count($existing) || array_diff($ids, $existing)) {
            return response()->json(['message' => __('curriculum.messages.reorder_mismatch')], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Lesson::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['message' => __('curriculum.messages.reordered')]);
    }

    public function trashed(Course $course): JsonResponse
    {
        Gate::authorize('view', $course);

        $lessons = Lesson::onlyTrashed()
            ->whereHas('section', fn($q) => $q->where('course_id', $course->id))
            ->with('section:id,title_ar,title_en')
            ->latest('deleted_at')
            ->get(['id', 'section_id', 'title_ar', 'title_en', 'content_type', 'deleted_at']);

        return response()->json(['data' => $lessons]);
    }

    public function restore(int $id): JsonResponse
    {
        $lesson = Lesson::onlyTrashed()->findOrFail($id);
        $course = $lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('update', $course);

        $lesson->restore();

        return response()->json(['message' => __('curriculum.messages.lesson_restored')]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $lesson = Lesson::onlyTrashed()->findOrFail($id);
        $course = $lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('forceDelete', $course);

        // لا يُحذف نهائيًا درس له تقدّم طلاب أو محاولات على اختباره
        $hasProgress = Schema::hasTable('lesson_progress')
            && DB::table('lesson_progress')->where('lesson_id', $lesson->id)->exists();

        if ($hasProgress || Quiz::attemptsExistForLesson($lesson->id)) {
            return response()->json(['message' => __('curriculum.messages.lesson_has_progress')], 409);
        }

        $lessonId = $lesson->id;
        DB::transaction(fn() => $lesson->forceDelete()); // المرفقات والاختبار يُحذفان بالتتالي
        Storage::disk('local')->deleteDirectory("lesson-attachments/{$lessonId}");

        return response()->json(['message' => __('curriculum.messages.lesson_force_deleted')]);
    }

    /* ---------- Helpers ---------- */

    /** الحقول غير المناسبة لنوع المحتوى تُفرَّغ */
    private function normalize(array $data): array
    {
        if (($data['content_type'] ?? null) !== 'video') {
            $data['video_provider'] = null;
            $data['video_url']      = null;
        }

        if (($data['content_type'] ?? null) !== 'article') {
            $data['content_ar'] = null;
            $data['content_en'] = null;
        }

        $data['duration_minutes'] = $data['duration_minutes'] ?? 0;

        return $data;
    }

    private function nextOrder(int $sectionId): int
    {
        return (int) Lesson::withTrashed()->where('section_id', $sectionId)->max('sort_order') + 1;
    }
}
