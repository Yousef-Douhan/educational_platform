<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\ProgressException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Services\ProgressService;
use App\Support\Loc;
use App\Support\VideoEmbed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مشغّل الدروس. الوصول: تسجيل فعّال في الدورة، أو درس معاينة مجانية.
 * قائمة المنهج لا تحمل رابط الفيديو ولا النص؛ المحتوى للدرس المفتوح فقط.
 */
class LearnController extends Controller
{
    use RespondsWithErrors;

    private const LIST_COLUMNS = ['id', 'section_id', 'title_ar', 'title_en', 'content_type', 'duration_minutes', 'is_free_preview', 'sort_order'];

    /** المنهج مع حالة الإكمال والدرس التالي */
    public function curriculum(Request $request, string $slug): JsonResponse
    {
        $course = $this->findCourse($slug);
        abort_unless($this->enrolled($request, $course), 403, __('api.not_enrolled'));

        $sections = $this->sections($course);
        $flat     = $sections->flatMap->lessons->values();
        $done     = $this->doneIds($request, $flat->pluck('id'));
        $next     = $flat->first(fn ($l) => ! in_array($l->id, $done, true)) ?? $flat->first();

        return response()->json(['data' => [
            'course'   => ['id' => $course->id, 'slug' => $course->slug, 'title' => Loc::pick($course->title_ar, $course->title_en)],
            'progress' => $this->progressOf($request, $course),
            'next_lesson_id' => $next?->id,
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id, 'title' => Loc::pick($s->title_ar, $s->title_en),
                'lessons' => $s->lessons->map(fn ($l) => $this->lessonSummary($l, in_array($l->id, $done, true)))->values(),
            ])->values(),
        ]]);
    }

    /** محتوى درس واحد */
    public function lesson(Request $request, string $slug, int $lesson): JsonResponse
    {
        $course   = $this->findCourse($slug);
        $enrolled = $this->enrolled($request, $course);

        $current = Lesson::query()
            ->whereIn('section_id', $course->sections()->select('id'))
            ->where('is_published', true)
            ->with(['attachments', 'quiz'])
            ->findOrFail($lesson);

        abort_unless($enrolled || $current->is_free_preview, 403, __('api.not_enrolled'));

        $flat  = $this->sections($course)->flatMap->lessons->values();
        $index = $flat->search(fn ($l) => $l->id === $current->id);
        $done  = $enrolled ? $this->doneIds($request, $flat->pluck('id')) : [];

        $quiz = null;
        if ($enrolled && $current->content_type === 'quiz' && $current->quiz) {
            $q        = $current->quiz;
            $attempts = QuizAttempt::where(['user_id' => $request->user()->id, 'quiz_id' => $q->id])
                ->where('status', '!=', 'voided')->orderByDesc('id')->limit(20)->get();
            $open = $attempts->first(fn ($a) => $a->status === 'in_progress' && ! $a->isPastDeadline());

            $quiz = [
                'id' => $q->id, 'title' => Loc::pick($q->title_ar, $q->title_en),
                'pass_mark' => $q->pass_mark, 'time_limit_minutes' => $q->time_limit_minutes,
                'attempts_allowed' => $q->attempts_allowed, 'attempts_used' => $attempts->count(),
                'open_attempt_id' => $open?->id,
                'passed' => in_array($current->id, $done, true),
            ];
        }

        return response()->json(['data' => $this->lessonSummary($current, in_array($current->id, $done, true)) + [
            'content'     => Loc::pick($current->content_ar, $current->content_en),
            'video'       => $current->content_type === 'video' ? VideoEmbed::resolve($current->video_provider, $current->video_url) : null,
            'attachments' => $current->attachments->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'size' => $a->file_size,
                'download_url' => route('api.attachments.download', $a->id),
            ])->values(),
            'quiz'        => $quiz,
            'prev_lesson_id' => $index !== false && $index > 0 ? $flat[$index - 1]->id : null,
            'next_lesson_id' => $index !== false ? ($flat[$index + 1]->id ?? null) : null,
            'enrolled'    => $enrolled,
        ]]);
    }

    public function complete(Request $request, ProgressService $progress, string $slug, int $lesson): JsonResponse
    {
        return $this->mark($request, $progress, $slug, $lesson, true);
    }

    public function uncomplete(Request $request, ProgressService $progress, string $slug, int $lesson): JsonResponse
    {
        return $this->mark($request, $progress, $slug, $lesson, false);
    }

    /** تنزيل مرفق درس: للمسجّل فقط (أو درس معاينة مجانية) */
    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file   = LessonAttachment::findOrFail($attachment);
        $lesson = $file->lesson()->with('section.course')->first();
        abort_if(! $lesson || $lesson->trashed() || ! $lesson->is_published, 404);

        $course = $lesson->section->course;
        abort_if($course->trashed() || ! in_array($course->status, ['published', 'archived'], true), 404);

        $allowed = $lesson->is_free_preview || Enrollment::grantingAccess()
            ->where(['user_id' => $request->user()->id, 'course_id' => $course->id])->exists();
        abort_unless($allowed, 403);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        $ext  = pathinfo($file->file_path, PATHINFO_EXTENSION);
        $base = trim((string) preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', $file->title)) ?: 'attachment';

        return Storage::disk('local')->download($file->file_path, $ext ? "{$base}.{$ext}" : $base);
    }

    /* ---------- داخلي ---------- */

    private function mark(Request $request, ProgressService $progress, string $slug, int $lessonId, bool $complete): JsonResponse
    {
        $course = $this->findCourse($slug);
        $lesson = Lesson::whereIn('section_id', $course->sections()->select('id'))->findOrFail($lessonId);

        try {
            $complete ? $progress->complete($request->user(), $lesson) : $progress->uncomplete($request->user(), $lesson);
        } catch (ProgressException $e) {
            return $this->fail($e);
        }

        return response()->json([
            'message'  => __($complete ? 'student.learn.completed' : 'student.learn.uncompleted'),
            'progress' => $this->progressOf($request, $course),
        ]);
    }

    private function lessonSummary(Lesson $l, bool $done): array
    {
        return [
            'id' => $l->id, 'title' => Loc::pick($l->title_ar, $l->title_en), 'type' => $l->content_type,
            'duration_minutes' => $l->duration_minutes, 'is_free_preview' => (bool) $l->is_free_preview,
            'completed' => $done,
        ];
    }

    private function progressOf(Request $request, Course $course): array
    {
        $p = CourseProgress::where(['user_id' => $request->user()->id, 'course_id' => $course->id])->first();

        return [
            'percent'   => (float) ($p?->progress_percentage ?? 0),
            'completed' => (int) ($p?->completed_lessons_count ?? 0),
            'total'     => (int) ($p?->total_lessons_count ?? 0),
            'finished'  => (bool) ($p?->is_finished ?? false),
        ];
    }

    private function sections(Course $course)
    {
        return $course->sections()->with(['lessons' => fn ($l) => $l->where('is_published', true)->select(self::LIST_COLUMNS)])->get();
    }

    private function findCourse(string $slug): Course
    {
        return Course::where('slug', $slug)->whereIn('status', ['published', 'archived'])->firstOrFail();
    }

    private function enrolled(Request $request, Course $course): bool
    {
        return Enrollment::grantingAccess()->where(['user_id' => $request->user()->id, 'course_id' => $course->id])->exists();
    }

    /** @return int[] */
    private function doneIds(Request $request, $lessonIds): array
    {
        return LessonProgress::where('user_id', $request->user()->id)->where('is_completed', true)
            ->whereIn('lesson_id', $lessonIds)->pluck('lesson_id')->map(fn ($v) => (int) $v)->all();
    }
}
