<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\QuizAttemptException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** التصحيح في الخادم فقط (QuizAttemptService). لا تُرسل الإجابات الصحيحة أبدًا. */
class QuizController extends Controller
{
    use RespondsWithErrors;

    public function start(Request $request, QuizAttemptService $attempts, int $quiz): JsonResponse
    {
        $quiz = Quiz::with('lesson.section.course')->findOrFail($quiz);

        try {
            $attempt = $attempts->start($request->user(), $quiz);
        } catch (QuizAttemptException $e) {
            return $this->fail($e);
        }

        return response()->json(['data' => $attempts->presentForStudent($attempt)], 201);
    }

    public function show(Request $request, QuizAttemptService $attempts, int $attempt): JsonResponse
    {
        $row = $this->own($request, $attempt);

        // انتهت مهلتها: تُغلق وتُصحَّح بما حُفظ فيها
        if ($row->status === 'in_progress' && $row->isPastDeadline()) {
            $row = $attempts->submit($request->user(), $row);
        }

        return response()->json(['data' => $row->status === 'in_progress'
            ? $attempts->presentForStudent($row)
            : $this->result($row)]);
    }

    public function submit(Request $request, QuizAttemptService $attempts, int $attempt): JsonResponse
    {
        $row   = $this->own($request, $attempt);
        $input = $request->validate([
            'answers'   => ['nullable', 'array', 'max:200'],
            'answers.*' => ['nullable', 'string', 'max:1000'],
        ])['answers'] ?? [];

        try {
            foreach ($input as $questionId => $value) {
                if (! is_int($questionId) && ! ctype_digit((string) $questionId)) {
                    continue;
                }
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $question = Question::where('id', (int) $questionId)->where('quiz_id', $row->quiz_id)->first();
                if (! $question) {
                    continue; // سؤال ليس من هذا الاختبار: يُتجاهل
                }
                $question->question_type === 'text'
                    ? $attempts->saveAnswer($request->user(), $row, $question->id, null, $value)
                    : $attempts->saveAnswer($request->user(), $row, $question->id, ctype_digit($value) ? (int) $value : null, null);
            }

            $row = $attempts->submit($request->user(), $row);
        } catch (QuizAttemptException $e) {
            // انتهت المهلة أثناء الحفظ: الخدمة أغلقتها، فنعرض النتيجة بدل الخطأ
            if ($e->reason === 'attempt_expired' || $e->reason === 'attempt_closed') {
                return response()->json(['data' => $this->result($row->fresh())]);
            }

            return $this->fail($e);
        }

        return response()->json(['data' => $this->result($row->fresh())]);
    }

    private function result(QuizAttempt $a): array
    {
        $quiz = Quiz::withTrashed()->find($a->quiz_id);

        return [
            'attempt_id' => $a->id, 'attempt_number' => $a->attempt_number, 'status' => $a->status,
            'quiz' => $quiz ? ['id' => $quiz->id, 'title' => Loc::pick($quiz->title_ar, $quiz->title_en)] : null,
            'score' => $a->score !== null ? (float) $a->score : null,
            'points_earned' => $a->points_earned, 'points_total' => $a->points_total,
            'pass_mark' => $a->pass_mark, 'is_passed' => (bool) $a->is_passed,
            'submitted_at' => $a->submitted_at?->toIso8601String(),
        ];
    }

    /** محاولة الطالب نفسه فقط؛ غيره 404 فلا يُكشف وجودها */
    private function own(Request $request, int $id): QuizAttempt
    {
        return QuizAttempt::where('user_id', $request->user()->id)->where('status', '!=', 'voided')->findOrFail($id);
    }
}
