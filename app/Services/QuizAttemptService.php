<?php

namespace App\Services;

use App\Exceptions\QuizAttemptException;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * كل منطق محاولات الاختبارات هنا. التصحيح يتم في الخادم فقط من بنية الأسئلة المخزّنة،
 * ولا تأتي الدرجة ولا صحة الإجابة من الطالب أبدًا.
 *
 *   start() ──► in_progress ──► submit()            ──► submitted   (درجة + نجاح/رسوب)
 *                          ├──► submit() بعد المهلة   ──► expired     (تُصحَّح الإجابات المحفوظة)
 *                          ├──► expireOverdue()     ──► expired     (مهمة مجدولة لمن لم يسلّم)
 *                          └──► void()              ──► voided      (إلغاء إداري، لا يُحسب من المحاولات)
 */
class QuizAttemptService
{
    /**
     * يبدأ محاولة جديدة، أو يعيد المحاولة الجارية إن كانت ما زالت ضمن وقتها (استئناف).
     * أي طالب لا يملك محاولتين جاريتين لاختبار واحد.
     */
    public function start(User $user, Quiz $quiz): QuizAttempt
    {
        return DB::transaction(function () use ($user, $quiz) {
            // نسلسل طلبات الطالب الواحد حتى لا تتسابق محاولتان
            $student = User::withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $quiz    = Quiz::withTrashed()->with('lesson.section.course')->findOrFail($quiz->getKey());

            $this->assertAvailable($student, $quiz);

            $open = QuizAttempt::where(['user_id' => $student->id, 'quiz_id' => $quiz->id, 'status' => 'in_progress'])->first();

            if ($open) {
                if (! $open->isPastDeadline()) {
                    return $open;
                }

                // انتهت مهلتها ولم تُسلَّم: تُغلق بما حُفظ فيها ثم نحتسب المحاولة المستهلكة
                $this->finalize($open, 'expired');
            }

            $used = QuizAttempt::where(['user_id' => $student->id, 'quiz_id' => $quiz->id])
                ->where('status', '!=', 'voided')->count();

            if ($quiz->attempts_allowed > 0 && $used >= $quiz->attempts_allowed) {
                throw QuizAttemptException::because('attempts_exhausted');
            }

            $questions = Question::where('quiz_id', $quiz->id)->get(['id', 'points']);

            if ($questions->isEmpty()) {
                throw QuizAttemptException::because('no_questions');
            }

            $attempt = new QuizAttempt;
            $attempt->forceFill([
                'user_id'        => $student->id,
                'quiz_id'        => $quiz->id,
                'attempt_number' => (int) QuizAttempt::where(['user_id' => $student->id, 'quiz_id' => $quiz->id])->max('attempt_number') + 1,
                'status'         => 'in_progress',
                'is_open'        => true,
                'points_total'   => (int) $questions->sum('points'),
                'pass_mark'      => $quiz->pass_mark,
                'started_at'     => now(),
                'expires_at'     => $quiz->time_limit_minutes > 0 ? now()->addMinutes($quiz->time_limit_minutes) : null,
            ])->save();

            return $attempt->refresh();
        });
    }

    /**
     * يحفظ (أو يستبدل) إجابة سؤال واحد. لا يُكتب أي تصحيح هنا.
     * اختيار من متعدد/صح وخطأ: $optionId. سؤال نصي: $text.
     */
    public function saveAnswer(User $user, QuizAttempt $attempt, int $questionId, ?int $optionId = null, ?string $text = null): QuizAnswer
    {
        $expired = false;

        $answer = DB::transaction(function () use ($user, $attempt, $questionId, $optionId, $text, &$expired) {
            $locked = $this->lock($attempt);
            $this->assertOwner($user, $locked);

            if ($locked->status !== 'in_progress') {
                throw QuizAttemptException::because('attempt_closed');
            }

            if ($locked->isPastDeadline()) {
                // تُغلق هنا ويُرمى الخطأ بعد تثبيت المعاملة (الرمي داخلها كان سيتراجع عن الإغلاق)
                $this->finalize($locked, 'expired');
                $expired = true;

                return null;
            }

            $question = Question::where('id', $questionId)->where('quiz_id', $locked->quiz_id)->first();

            if (! $question) {
                throw QuizAttemptException::because('question_not_in_quiz');
            }

            [$optionId, $text] = $this->validAnswer($question, $optionId, $text);

            $row = QuizAnswer::where(['attempt_id' => $locked->id, 'question_id' => $question->id])->first() ?? new QuizAnswer;
            $row->forceFill([
                'attempt_id'     => $locked->id,
                'question_id'    => $question->id,
                'option_id'      => $optionId,
                'answer_text'    => $text,
                'is_correct'     => false,
                'points_awarded' => 0,
            ])->save();

            return $row;
        });

        if ($expired) {
            throw QuizAttemptException::because('attempt_expired');
        }

        return $answer;
    }

    /** تسليم المحاولة وتصحيحها. تكرار التسليم يعيد النتيجة نفسها دون إعادة حساب */
    public function submit(User $user, QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($user, $attempt) {
            $locked = $this->lock($attempt);
            $this->assertOwner($user, $locked);

            if (in_array($locked->status, ['submitted', 'expired'], true)) {
                return $locked;
            }

            if ($locked->status === 'voided') {
                throw QuizAttemptException::because('attempt_closed');
            }

            // التسليم المتأخر لا يُهدر عمل الطالب: يُصحَّح لكن يُسجَّل expired
            return $this->finalize($locked, $locked->isPastGrace() ? 'expired' : 'submitted');
        });
    }

    /** يغلق ويصحح المحاولات الجارية التي انتهت مهلتها (مهمة مجدولة). يعيد عددها */
    public function expireOverdue(): int
    {
        $closed = 0;

        QuizAttempt::where('status', 'in_progress')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subSeconds(QuizAttempt::GRACE_SECONDS))
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use (&$closed) {
                foreach ($attempts as $attempt) {
                    DB::transaction(function () use ($attempt, &$closed) {
                        $locked = $this->lock($attempt);

                        if ($locked->status === 'in_progress' && $locked->isPastGrace()) {
                            $this->finalize($locked, 'expired');
                            $closed++;
                        }
                    });
                }
            });

        return $closed;
    }

    /** إلغاء إداري (غش مثلًا): تبقى الدرجات للتدقيق، لكن المحاولة لا تُحسب ولا تُعدّ نجاحًا */
    public function void(QuizAttempt $attempt, User $by, string $reason): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $by, $reason) {
            $locked = $this->lock($attempt);

            if ($locked->status === 'voided') {
                throw QuizAttemptException::because('already_voided');
            }

            $locked->forceFill([
                'status'      => 'voided',
                'is_open'     => null,
                'is_passed'   => false,
                'voided_at'   => now(),
                'voided_by'   => $by->id,
                'void_reason' => mb_substr(trim($reason), 0, 500),
            ])->save();

            return $locked;
        });
    }

    /**
     * ما يُرسل للطالب أثناء المحاولة: الأسئلة وخياراتها دون أي إشارة للصحيح،
     * مع إجاباته المحفوظة والوقت المتبقي.
     */
    public function presentForStudent(QuizAttempt $attempt): array
    {
        $questions = Question::where('quiz_id', $attempt->quiz_id)->with('options')->orderBy('sort_order')->orderBy('id')->get();
        $saved     = QuizAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        return [
            'attempt_id'        => $attempt->id,
            'attempt_number'    => $attempt->attempt_number,
            'expires_at'        => $attempt->expires_at?->toIso8601String(),
            'remaining_seconds' => $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null,
            'questions'         => $questions->map(fn (Question $q) => [
                'id'      => $q->id,
                'text'    => $q->question_text,
                'type'    => $q->question_type,
                'points'  => $q->points,
                // نص الخيار فقط: لا is_correct. أسئلة النص لا تكشف الإجابات المقبولة
                'options' => $q->question_type === 'text' ? [] : $q->options->map(fn (QuestionOption $o) => [
                    'id' => $o->id, 'text' => $o->option_text,
                ])->values(),
                'answer'  => $saved->has($q->id)
                    ? ['option_id' => $saved[$q->id]->option_id, 'text' => $saved[$q->id]->answer_text]
                    : null,
            ])->values()->all(),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function assertAvailable(User $student, Quiz $quiz): void
    {
        if ($student->trashed()) {
            throw QuizAttemptException::because('student_inactive');
        }

        $lesson = $quiz->lesson;

        if ($quiz->trashed() || $lesson->trashed() || ! $lesson->is_published || $lesson->section->course->trashed()) {
            throw QuizAttemptException::because('quiz_unavailable');
        }

        $enrolled = Enrollment::grantingAccess()
            ->where(['user_id' => $student->id, 'course_id' => $lesson->section->course_id])
            ->exists();

        if (! $enrolled) {
            throw QuizAttemptException::because('not_enrolled');
        }
    }

    /** يتحقق من شكل الإجابة حسب نوع السؤال ويعيد [option_id, text] المعتمدين */
    private function validAnswer(Question $question, ?int $optionId, ?string $text): array
    {
        if ($question->question_type === 'text') {
            $text = trim((string) $text);

            if ($text === '' || mb_strlen($text) > 1000) {
                throw QuizAttemptException::because('answer_invalid');
            }

            return [null, $text];
        }

        $valid = $optionId && QuestionOption::where('id', $optionId)->where('question_id', $question->id)->exists();

        if (! $valid) {
            throw QuizAttemptException::because('answer_invalid');
        }

        return [$optionId, null];
    }

    private function isCorrect(Question $question, ?QuizAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        if ($question->question_type === 'text') {
            $given = $this->normalize((string) $answer->answer_text);

            return $given !== '' && $question->options->contains(
                fn (QuestionOption $o) => $o->is_correct && $this->normalize($o->option_text) === $given
            );
        }

        return $answer->option_id !== null
            && $question->options->contains(fn (QuestionOption $o) => $o->is_correct && $o->id === $answer->option_id);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /** يصحح ويغلق المحاولة (يُستدعى والصف مقفول داخل معاملة) */
    private function finalize(QuizAttempt $attempt, string $status): QuizAttempt
    {
        $questions = Question::where('quiz_id', $attempt->quiz_id)->with('options')->get();
        $answers   = QuizAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id');

        $earned = 0;
        $total  = 0;

        foreach ($questions as $question) {
            $answer  = $answers->get($question->id) ?? (new QuizAnswer)->forceFill(['attempt_id' => $attempt->id, 'question_id' => $question->id]);
            $correct = $this->isCorrect($question, $answers->get($question->id));
            $points  = $correct ? (int) $question->points : 0;

            $answer->forceFill(['is_correct' => $correct, 'points_awarded' => $points])->save();

            $total  += (int) $question->points;
            $earned += $points;
        }

        // إجابات أسئلة حُذفت مؤقتًا أثناء المحاولة لا تدخل في الدرجة
        QuizAnswer::where('attempt_id', $attempt->id)
            ->whereNotIn('question_id', $questions->pluck('id'))
            ->update(['is_correct' => false, 'points_awarded' => 0]);

        $score = $total > 0 ? round($earned / $total * 100, 2) : 0;

        $attempt->forceFill([
            'status'        => $status,
            'is_open'       => null,
            'points_earned' => $earned,
            'points_total'  => $total,
            'score'         => $score,
            'is_passed'     => $total > 0 && $score >= $attempt->pass_mark,
            'submitted_at'  => now(),
        ])->save();

        return $attempt;
    }

    private function lock(QuizAttempt $attempt): QuizAttempt
    {
        return QuizAttempt::whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertOwner(User $user, QuizAttempt $attempt): void
    {
        if ($attempt->user_id !== $user->id) {
            throw QuizAttemptException::because('not_your_attempt');
        }
    }
}
