<?php

namespace App\Services;

use App\Exceptions\ProgressException;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * كل كتابة في lesson_progress و course_progress تمرّ من هنا. النسبة تُحسب دائمًا في الخادم
 * من الدروس المنشورة غير المحذوفة، ولا يرسلها الطالب.
 *
 *   complete()          ─┐
 *   recordQuizPass()    ─┼─► lesson_progress ──► recalculate() ──► course_progress
 *   uncomplete()        ─┤                                    └─► التسجيل active ► completed عند 100%
 *   revokeQuizLesson()  ─┘
 *
 * دروس الاختبار لا تُكمل ولا يُلغى إكمالها يدويًا: نجاح الاختبار وحده هو الدليل.
 * إلغاء الإكمال لا يعيد التسجيل المكتمل إلى active، حتى لا تضيع أهلية شهادة صدرت أو ستصدر.
 */
class ProgressService
{
    /** يكمل درسًا للطالب. تكراره لا يغيّر وقت الإكمال الأول */
    public function complete(User $user, Lesson $lesson): CourseProgress
    {
        return DB::transaction(function () use ($user, $lesson) {
            $this->lockStudent($user->getKey());
            $lesson = $this->learnable($user->getKey(), $lesson->getKey());

            if ($lesson->content_type === 'quiz' && ! $this->hasPassedQuiz($user->getKey(), $lesson->id)) {
                throw ProgressException::because('quiz_not_passed');
            }

            $this->mark($user->getKey(), $lesson->id, true);

            return $this->recalculate($user->getKey(), $lesson->section->course_id);
        });
    }

    /** يلغي إكمال درس عادي. درس الاختبار مقفول لأن إكماله مثبت بنتيجة الاختبار */
    public function uncomplete(User $user, Lesson $lesson): CourseProgress
    {
        return DB::transaction(function () use ($user, $lesson) {
            $this->lockStudent($user->getKey());
            $lesson = $this->learnable($user->getKey(), $lesson->getKey());

            if ($lesson->content_type === 'quiz') {
                throw ProgressException::because('quiz_lesson_locked');
            }

            $this->mark($user->getKey(), $lesson->id, false);

            return $this->recalculate($user->getKey(), $lesson->section->course_id);
        });
    }

    /**
     * يُستدعى من QuizAttemptService عند نجاح محاولة. صامت عمدًا: فشل التقدم (انتهى التسجيل
     * أو أُلغي نشر الدرس مثلًا) لا يجب أن يُبطل تصحيح الاختبار نفسه.
     */
    public function recordQuizPass(int $userId, int $quizId): void
    {
        DB::transaction(function () use ($userId, $quizId) {
            $this->lockStudent($userId);

            $lessonId = DB::table('quizzes')->where('id', $quizId)->value('lesson_id');

            try {
                $lesson = $lessonId ? $this->learnable($userId, (int) $lessonId) : null;
            } catch (ProgressException) {
                $lesson = null;
            }

            if (! $lesson) {
                return;
            }

            $this->mark($userId, $lesson->id, true);
            $this->recalculate($userId, $lesson->section->course_id);
        });
    }

    /** بعد إلغاء محاولة ناجحة: إن لم تبقَ محاولة ناجحة أخرى يُسحب إكمال درس الاختبار */
    public function revokeQuizLesson(int $userId, int $quizId): void
    {
        DB::transaction(function () use ($userId, $quizId) {
            $this->lockStudent($userId);

            $lesson = Lesson::withTrashed()->with('section')->find(DB::table('quizzes')->where('id', $quizId)->value('lesson_id'));

            if (! $lesson || $this->hasPassedQuiz($userId, $lesson->id)) {
                return;
            }

            $row = LessonProgress::where(['user_id' => $userId, 'lesson_id' => $lesson->id, 'is_completed' => true])->first();

            if ($row) {
                $this->mark($userId, $lesson->id, false);
                $this->recalculate($userId, $lesson->section->course_id);
            }
        });
    }

    /**
     * يعيد حساب ملخص الطالب في الدورة من الصفر. آمن للتكرار ويصحح أي انحراف
     * (درس أُضيف أو حُذف أو أُلغي نشره بعد أن أكمل الطالب جزءًا).
     */
    public function recalculate(int|User $user, int|Course $course): CourseProgress
    {
        $userId   = $user instanceof User ? $user->getKey() : $user;
        $courseId = $course instanceof Course ? $course->getKey() : $course;

        return DB::transaction(function () use ($userId, $courseId) {
            $this->lockStudent($userId);

            $lessonIds = fn () => DB::table('lessons')
                ->join('course_sections', 'course_sections.id', '=', 'lessons.section_id')
                ->where('course_sections.course_id', $courseId)
                ->where('lessons.is_published', true)
                ->whereNull('lessons.deleted_at')
                ->select('lessons.id');

            $total = $lessonIds()->count();
            $done  = LessonProgress::where(['user_id' => $userId, 'is_completed' => true])
                ->whereIn('lesson_id', $lessonIds())->count();

            $percent  = $total > 0 ? round($done / $total * 100, 2) : 0;
            $finished = $total > 0 && $done >= $total;

            $row = $this->firstOrNew(CourseProgress::class, ['user_id' => $userId, 'course_id' => $courseId]);
            $row->forceFill([
                'completed_lessons_count' => $done,
                'total_lessons_count'     => $total,
                'progress_percentage'     => $percent,
                'is_finished'             => $finished,
                // وقت الإنهاء الأول يبقى ثابتًا، ويُمسح إن لم تعد الدورة منتهية
                'finished_at'             => $finished ? ($row->finished_at ?? now()) : null,
            ])->save();

            if ($finished) {
                Enrollment::where(['user_id' => $userId, 'course_id' => $courseId, 'status' => 'active'])
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->update(['status' => 'completed']);
            }

            return $row;
        });
    }

    /** يعيد حساب كل أزواج (طالب، دورة) التي لها تقدم؛ لدورة واحدة أو للكل. يعيد عددها */
    public function recalculateAll(?int $courseId = null): int
    {
        $fromLessons = DB::table('lesson_progress')
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.section_id')
            ->when($courseId, fn ($q) => $q->where('course_sections.course_id', $courseId))
            ->select('lesson_progress.user_id as user_id', 'course_sections.course_id as course_id');

        $pairs = DB::table('course_progress')
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->select('user_id', 'course_id')
            ->union($fromLessons)
            ->get();

        foreach ($pairs as $pair) {
            $this->recalculate((int) $pair->user_id, (int) $pair->course_id);
        }

        return $pairs->count();
    }

    /* ------------------------------------------------------------------ */

    /** الدرس متاح للتعلم: منشور وغير محذوف ودورته قائمة، والطالب مسجَّل تسجيلًا فعّالًا */
    private function learnable(int $userId, int $lessonId): Lesson
    {
        $student = User::withTrashed()->find($userId);
        $lesson  = Lesson::withTrashed()->with('section.course')->find($lessonId);

        if (! $student || $student->trashed()) {
            throw ProgressException::because('student_inactive');
        }

        if (! $lesson || $lesson->trashed() || ! $lesson->is_published || $lesson->section->course->trashed()) {
            throw ProgressException::because('lesson_unavailable');
        }

        $enrolled = Enrollment::grantingAccess()
            ->where(['user_id' => $userId, 'course_id' => $lesson->section->course_id])
            ->exists();

        if (! $enrolled) {
            throw ProgressException::because('not_enrolled');
        }

        return $lesson;
    }

    private function hasPassedQuiz(int $userId, int $lessonId): bool
    {
        return QuizAttempt::where(['user_id' => $userId, 'is_passed' => true])
            ->whereIn('status', ['submitted', 'expired'])
            ->whereIn('quiz_id', DB::table('quizzes')->where('lesson_id', $lessonId)->select('id'))
            ->exists();
    }

    private function mark(int $userId, int $lessonId, bool $completed): LessonProgress
    {
        $row = $this->firstOrNew(LessonProgress::class, ['user_id' => $userId, 'lesson_id' => $lessonId]);

        if ($completed) {
            // الإكمال الأول هو المسجَّل: تكرار الطلب لا يحرّك الوقت
            $row->forceFill(['is_completed' => true, 'completed_at' => $row->is_completed ? $row->completed_at : now()])->save();
        } else {
            $row->forceFill(['is_completed' => false, 'completed_at' => null])->save();
        }

        return $row;
    }

    /**
     * يجلب الصف أو يجهّز واحدًا جديدًا ويحفظ الأرقام الأساسية. إن سبقه طلب متزامن
     * (القيد الفريد) نعيد جلب صفه بدل الفشل.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $class
     * @return T
     */
    private function firstOrNew(string $class, array $keys)
    {
        $row = $class::where($keys)->first();

        if ($row) {
            return $row;
        }

        $row = new $class;
        $row->forceFill($keys);

        try {
            $row->save();
        } catch (UniqueConstraintViolationException) {
            return $class::where($keys)->firstOrFail();
        }

        return $row;
    }

    /** يسلسل عمليات الطالب الواحد: الترتيب user ثم الباقي في كل الخدمات لتفادي الأقفال المتبادلة */
    private function lockStudent(int $userId): void
    {
        User::withTrashed()->whereKey($userId)->lockForUpdate()->first();
    }
}
