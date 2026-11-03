<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\ProgressException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\CourseProgress;
use App\Services\ProgressService;
use App\Support\VideoEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مشغّل الدروس. الوصول: تسجيل فعّال في الدورة، أو درس معاينة مجانية.
 * محتوى الدرس (رابط الفيديو والنص) يُحمَّل للدرس المعروض فقط، لا لقائمة الدروس الجانبية.
 */
class LearnController extends Controller
{
    private const LIST_COLUMNS = ['id', 'section_id', 'title_ar', 'title_en', 'content_type', 'duration_minutes', 'is_free_preview', 'sort_order'];

    /** يفتح أول درس غير مكتمل (أو الأول إن اكتملت كلها) */
    public function course(Request $request, string $slug): RedirectResponse
    {
        $course = $this->findCourse($slug);
        abort_unless($this->enrolled($request, $course), 403);

        $lessons = $this->orderedLessons($course);

        if ($lessons->isEmpty()) {
            return redirect()->route('student.courses.show', $course->slug)->withErrors(['learn' => __('student.learn.no_lessons')]);
        }

        $done = $this->doneIds($request, $lessons->pluck('id'));
        $next = $lessons->first(fn ($l) => ! in_array($l->id, $done, true)) ?? $lessons->first();

        return redirect()->route('student.learn.lesson', [$course->slug, $next->id]);
    }

    public function lesson(Request $request, string $slug, int $lesson): View|RedirectResponse
    {
        $course   = $this->findCourse($slug);
        $enrolled = $this->enrolled($request, $course);

        $current = Lesson::query()
            ->whereIn('section_id', $course->sections()->select('id'))
            ->where('is_published', true)
            ->with(['attachments', 'quiz'])
            ->findOrFail($lesson);

        if (! $enrolled && ! $current->is_free_preview) {
            return $request->user()
                ? redirect()->route('student.courses.show', $course->slug)->withErrors(['learn' => __('student.learn.not_enrolled')])
                : redirect()->guest(route('login'));
        }

        $sections = $course->sections()->with(['lessons' => fn ($l) => $l->where('is_published', true)->select(self::LIST_COLUMNS)])->get();
        $flat     = $sections->flatMap->lessons->values();
        $index    = $flat->search(fn ($l) => $l->id === $current->id);

        $done = $enrolled ? $this->doneIds($request, $flat->pluck('id')) : [];

        $quizState = null;
        if ($enrolled && $current->content_type === 'quiz' && $current->quiz) {
            $attempts = QuizAttempt::where(['user_id' => $request->user()->id, 'quiz_id' => $current->quiz->id])
                ->where('status', '!=', 'voided')->orderByDesc('id')->limit(10)->get();

            $quizState = [
                'quiz'     => $current->quiz,
                'attempts' => $attempts,
                'open'     => $attempts->first(fn ($a) => $a->status === 'in_progress' && ! $a->isPastDeadline()),
                'used'     => $attempts->count(),
                'passed'   => in_array($current->id, $done, true),
            ];
        }

        return view('student.learn.lesson', [
            'course'    => $course,
            'sections'  => $sections,
            'lesson'    => $current,
            'enrolled'  => $enrolled,
            'done'      => $done,
            'prev'      => $index !== false && $index > 0 ? $flat[$index - 1] : null,
            'next'      => $index !== false ? ($flat[$index + 1] ?? null) : null,
            'video'     => $current->content_type === 'video' ? VideoEmbed::resolve($current->video_provider, $current->video_url) : null,
            'quizState' => $quizState,
            'percent'   => $enrolled
                ? (float) (CourseProgress::where(['user_id' => $request->user()->id, 'course_id' => $course->id])->value('progress_percentage') ?? 0)
                : null,
        ]);
    }

    public function complete(Request $request, ProgressService $progress, string $slug, int $lesson): RedirectResponse
    {
        return $this->mark($request, $progress, $slug, $lesson, true);
    }

    public function uncomplete(Request $request, ProgressService $progress, string $slug, int $lesson): RedirectResponse
    {
        return $this->mark($request, $progress, $slug, $lesson, false);
    }

    /* ---------- داخلي ---------- */

    private function mark(Request $request, ProgressService $progress, string $slug, int $lessonId, bool $complete): RedirectResponse
    {
        $course = $this->findCourse($slug);
        $lesson = Lesson::whereIn('section_id', $course->sections()->select('id'))->findOrFail($lessonId);

        try {
            $complete ? $progress->complete($request->user(), $lesson) : $progress->uncomplete($request->user(), $lesson);
        } catch (ProgressException $e) {
            return back()->withErrors(['learn' => $e->getMessage()]);
        }

        if ($complete) {
            $flat = $this->orderedLessons($course);
            $i    = $flat->search(fn ($l) => $l->id === $lesson->id);
            $next = $i !== false ? ($flat[$i + 1] ?? null) : null;

            if ($next) {
                return redirect()->route('student.learn.lesson', [$course->slug, $next->id])->with('status', __('student.learn.completed'));
            }
        }

        return redirect()->route('student.learn.lesson', [$course->slug, $lesson->id])
            ->with('status', __($complete ? 'student.learn.completed_last' : 'student.learn.uncompleted'));
    }

    private function findCourse(string $slug): Course
    {
        return Course::where('slug', $slug)->whereIn('status', ['published', 'archived'])->firstOrFail();
    }

    private function enrolled(Request $request, Course $course): bool
    {
        $user = $request->user();

        return $user !== null && Enrollment::grantingAccess()->where(['user_id' => $user->id, 'course_id' => $course->id])->exists();
    }

    private function orderedLessons(Course $course)
    {
        return $course->sections()->with(['lessons' => fn ($l) => $l->where('is_published', true)->select(self::LIST_COLUMNS)])
            ->get()->flatMap->lessons->values();
    }

    /** @return int[] */
    private function doneIds(Request $request, $lessonIds): array
    {
        return LessonProgress::where('user_id', $request->user()->id)->where('is_completed', true)
            ->whereIn('lesson_id', $lessonIds)->pluck('lesson_id')->map(fn ($v) => (int) $v)->all();
    }
}
