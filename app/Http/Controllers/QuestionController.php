<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionRequest;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuestionOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuestionController extends Controller implements HasMiddleware
{
    public function __construct(private readonly QuestionOptionsService $options) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:quizzes.view',   only: ['index', 'trashed']),
            new Middleware('permission:quizzes.edit',   only: ['store', 'update', 'destroy', 'reorder', 'restore']),
            new Middleware('permission:quizzes.delete', only: ['forceDelete']),
        ];
    }

    /** منشئ الأسئلة: الاختبار وأسئلته بخياراتها المرتبة */
    public function index(Request $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('view', $quiz);

        $questions = $quiz->questions()->with('options')->get();
        $course    = $quiz->owningCourse();

        return response()->json([
            'quiz'      => $quiz->only(['id', 'title_ar', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed']),
            'course'    => $course->only(['id', 'title_ar', 'title_en']),
            'questions' => $questions->map(fn(Question $q) => $this->present($q))->values(),
            'totals'    => [
                'questions' => $questions->count(),
                'points'    => (int) $questions->sum('points'),
                'trashed'   => $quiz->questions()->onlyTrashed()->count(),
            ],
            'locked'           => $quiz->hasAttempts(),
            'can_edit'         => $request->user()->can('update', $quiz),
            'can_force_delete' => $request->user()->can('forceDelete', $quiz),
        ]);
    }

    public function store(QuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $data = $request->validated();
        $rows = $this->options->normalize($data['question_type'], $data);

        $question = DB::transaction(function () use ($quiz, $data, $rows) {
            $question = $quiz->questions()->create([
                'question_text' => $data['question_text'],
                'question_type' => $data['question_type'],
                'points'        => $data['points'],
                'sort_order'    => (int) Question::withTrashed()->where('quiz_id', $quiz->id)->max('sort_order') + 1,
            ]);

            $this->options->sync($question, $rows);

            return $question;
        });

        return response()->json([
            'message' => __('quizzes.messages.question_created'),
            'data'    => $this->present($question->load('options')),
        ], 201);
    }

    public function update(QuestionRequest $request, Question $question): JsonResponse
    {
        $quiz = $question->quiz;
        abort_if($quiz->trashed(), 404);

        $data = $request->validated();
        $rows = $this->options->normalize($data['question_type'], $data, $question);

        if ($quiz->hasAttempts() && $this->options->structureChanged($question, $data['question_type'], (int) $data['points'], $rows)) {
            return response()->json(['message' => __('quizzes.messages.structure_locked')], 409);
        }

        DB::transaction(function () use ($question, $data, $rows) {
            $question->update(['question_text' => $data['question_text'], 'question_type' => $data['question_type'], 'points' => $data['points']]);
            $this->options->sync($question, $rows);
        });

        return response()->json([
            'message' => __('quizzes.messages.question_updated'),
            'data'    => $this->present($question->fresh('options')),
        ]);
    }

    /** حذف مؤقت: مسموح حتى بعد المحاولات لأنه لا يمسّ سجلاتها */
    public function destroy(Question $question): JsonResponse
    {
        $quiz = $question->quiz;
        abort_if($quiz->trashed(), 404);
        Gate::authorize('update', $quiz);

        $question->delete();

        return response()->json(['message' => __('quizzes.messages.question_deleted')]);
    }

    public function reorder(Request $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        $ids      = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $existing = Question::where('quiz_id', $quiz->id)->pluck('id')->all();

        if (count($ids) !== count($existing) || array_diff($ids, $existing)) {
            return response()->json(['message' => __('quizzes.messages.reorder_mismatch')], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Question::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['message' => __('quizzes.messages.reordered')]);
    }

    public function trashed(Quiz $quiz): JsonResponse
    {
        Gate::authorize('view', $quiz);

        return response()->json([
            'data' => $quiz->questions()->onlyTrashed()->latest('deleted_at')
                ->get(['id', 'question_text', 'question_type', 'points', 'deleted_at']),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $question = Question::onlyTrashed()->findOrFail($id);
        $quiz     = $question->quiz;
        Gate::authorize('update', $quiz);

        if ($quiz->trashed()) {
            return response()->json(['message' => __('quizzes.messages.parent_trashed')], 409);
        }

        $question->restore();

        return response()->json(['message' => __('quizzes.messages.question_restored')]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $question = Question::onlyTrashed()->findOrFail($id);
        $quiz     = $question->quiz;
        Gate::authorize('forceDelete', $quiz);

        if ($quiz->hasAttempts()) {
            return response()->json(['message' => __('quizzes.messages.has_attempts')], 409);
        }

        $question->forceDelete();

        return response()->json(['message' => __('quizzes.messages.question_force_deleted')]);
    }

    private function present(Question $q): array
    {
        return [
            'id'            => $q->id,
            'question_text' => $q->question_text,
            'question_type' => $q->question_type,
            'points'        => $q->points,
            'sort_order'    => $q->sort_order,
            'options'       => $q->options->map(fn($o) => [
                'id' => $o->id,
                'option_text' => $o->option_text,
                'is_correct' => $o->is_correct,
                'sort_order' => $o->sort_order,
            ])->values(),
        ];
    }
}
