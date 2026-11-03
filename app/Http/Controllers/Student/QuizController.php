<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\QuizAttemptException;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** محاولات الاختبار للطالب. التصحيح في الخادم فقط (QuizAttemptService) ولا تُرسل الإجابات الصحيحة أبدًا */
class QuizController extends Controller
{
    public function start(Request $request, QuizAttemptService $attempts, int $quiz): RedirectResponse
    {
        $quiz = Quiz::with('lesson.section.course')->findOrFail($quiz);

        try {
            $attempt = $attempts->start($request->user(), $quiz);
        } catch (QuizAttemptException $e) {
            return back()->withErrors(['quiz' => $e->getMessage()]);
        }

        return redirect()->route('student.attempts.show', $attempt->id);
    }

    public function show(Request $request, QuizAttemptService $attempts, int $attempt): View|RedirectResponse
    {
        $row = $this->own($request, $attempt);

        // انتهت مهلتها: تُغلق وتُصحَّح بما حُفظ فيها
        if ($row->status === 'in_progress' && $row->isPastDeadline()) {
            $row = $attempts->submit($request->user(), $row);
        }

        return view('student.learn.attempt', [
            'attempt' => $row,
            'quiz'    => $row->quiz()->with('lesson.section.course')->first(),
            'data'    => $row->status === 'in_progress' ? $attempts->presentForStudent($row) : null,
        ]);
    }

    public function submit(Request $request, QuizAttemptService $attempts, int $attempt): RedirectResponse
    {
        $row = $this->own($request, $attempt);

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

                $question = \App\Models\Question::where('id', (int) $questionId)->where('quiz_id', $row->quiz_id)->first();

                if (! $question) {
                    continue; // سؤال ليس من هذا الاختبار: يُتجاهل
                }

                $question->question_type === 'text'
                    ? $attempts->saveAnswer($request->user(), $row, $question->id, null, $value)
                    : $attempts->saveAnswer($request->user(), $row, $question->id, ctype_digit($value) ? (int) $value : null, null);
            }

            $attempts->submit($request->user(), $row);
        } catch (QuizAttemptException $e) {
            // انتهت المهلة أثناء الحفظ: الخدمة أغلقتها، فنعرض النتيجة بدل الخطأ
            if ($e->reason === 'attempt_expired' || $e->reason === 'attempt_closed') {
                return redirect()->route('student.attempts.show', $row->id);
            }

            return back()->withErrors(['quiz' => $e->getMessage()])->withInput();
        }

        return redirect()->route('student.attempts.show', $row->id);
    }

    /** محاولة الطالب نفسه فقط؛ غيره يحصل على 404 لا 403 فلا يُكشف وجودها */
    private function own(Request $request, int $id): QuizAttempt
    {
        return QuizAttempt::where('user_id', $request->user()->id)->where('status', '!=', 'voided')->findOrFail($id);
    }
}
