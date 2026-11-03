<?php

namespace App\Http\Controllers;

use App\Exceptions\QuizAttemptException;
use App\Http\Requests\QuizAttemptVoidRequest;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * مراجعة محاولات الطلاب في الاختبارات. المحاولات تُنشأ وتُصحَّح من QuizAttemptService
 * عند دراسة الطالب، فلا إنشاء ولا تعديل ولا حذف هنا؛ المدير يراجع ويُلغي المشبوه فقط.
 * المدير يرى الكل، والمدرّس يرى محاولات اختبارات دوراته.
 */
class QuizAttemptController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'score', 'status', 'started_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:quiz-attempts.view', only: ['index', 'show']),
            new Middleware('permission:quiz-attempts.void', only: ['void']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'started_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $attempts = QuizAttempt::query()
            ->visibleTo($user)
            ->with(['user', 'quiz'])
            ->search($request->get('search'))
            ->withStatus($request->get('status'))
            ->withResult($request->get('result'))
            ->forQuiz($request->filled('quiz_id') ? (int) $request->get('quiz_id') : null)
            ->startedBetween($this->date($request->get('from')), $this->date($request->get('to')))
            ->orderBy("quiz_attempts.{$sort}", $dir)
            ->orderBy('quiz_attempts.id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.quiz-attempts.partials.table', compact('attempts'))->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.quiz-attempts.index', [
            'attempts' => $attempts,
            'stats'    => $this->stats($user),
            'quizzes'  => Quiz::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    public function show(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request->user(), $attempt);

        return response()->json(['data' => $this->payload($attempt, $request->user())]);
    }

    public function void(QuizAttemptVoidRequest $request, QuizAttempt $attempt, QuizAttemptService $service): JsonResponse
    {
        $this->authorizeAttempt($request->user(), $attempt);

        try {
            $service->void($attempt, $request->user(), $request->validated('reason'));
        } catch (QuizAttemptException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => __('quiz_attempts.messages.voided', ['id' => $attempt->id]),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /* ---------- داخلي ---------- */

    /** المدرّس لا يرى محاولة اختبار خارج دوراته: نخفيها كأنها غير موجودة */
    private function authorizeAttempt(User $user, QuizAttempt $attempt): void
    {
        abort_unless(QuizAttempt::visibleTo($user)->whereKey($attempt->id)->exists(), 404);
    }

    private function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function stats(User $user): array
    {
        $base   = fn () => QuizAttempt::query()->visibleTo($user);
        $graded = fn () => $base()->whereIn('status', ['submitted', 'expired']);

        return [
            'total'       => $base()->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'passed'      => $graded()->where('is_passed', true)->count(),
            'failed'      => $graded()->where('is_passed', false)->count(),
            'average'     => number_format((float) $graded()->avg('score'), 1),
        ];
    }

    private function payload(QuizAttempt $attempt, User $me): array
    {
        $attempt->load(['user', 'quiz.lesson.section.course', 'voidedBy']);
        $tz     = config('app.timezone');
        $fmt    = fn ($d) => $d?->copy()->timezone($tz)->format('Y-m-d H:i');
        $graded = $attempt->submitted_at !== null;

        $answers = QuizAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->with(['question.options', 'option'])
            ->get()
            ->sortBy(fn (QuizAnswer $a) => sprintf('%08d-%08d', $a->question->sort_order, $a->question_id))
            ->values();

        $course = $attempt->quiz->lesson->section->course;

        return [
            'id'          => $attempt->id,
            'number'      => $attempt->attempt_number,
            'status'      => $attempt->status,
            'user'        => ['name' => $attempt->user->name, 'email' => $attempt->user->email, 'deleted' => $attempt->user->trashed()],
            'quiz'        => ['title_ar' => $attempt->quiz->title_ar, 'title_en' => $attempt->quiz->title_en],
            'course'      => ['title_ar' => $course->title_ar, 'title_en' => $course->title_en],
            'score'       => $attempt->score,
            'points'      => ['earned' => $attempt->points_earned, 'total' => $attempt->points_total],
            'pass_mark'   => $attempt->pass_mark,
            'is_passed'   => $attempt->is_passed,
            'graded'      => $graded,
            'started_at'  => $fmt($attempt->started_at),
            'expires_at'  => $fmt($attempt->expires_at),
            'submitted_at' => $fmt($attempt->submitted_at),
            'duration_minutes' => $attempt->submitted_at ? max(0, (int) round($attempt->started_at->diffInSeconds($attempt->submitted_at) / 60)) : null,
            'voided_at'   => $fmt($attempt->voided_at),
            'voided_by'   => $attempt->voidedBy?->name,
            'void_reason' => $attempt->void_reason,
            'answers'     => $answers->map(fn (QuizAnswer $a) => [
                'question'    => $a->question->question_text,
                'type'        => $a->question->question_type,
                'points'      => $a->question->points,
                'given'       => $a->question->question_type === 'text' ? $a->answer_text : $a->option?->option_text,
                'correct'     => $a->question->options->where('is_correct', true)->pluck('option_text')->values()->all(),
                'is_correct'  => $graded ? $a->is_correct : null,
                'awarded'     => $a->points_awarded,
                'question_deleted' => $a->question->trashed(),
            ])->all(),
            'can' => ['void' => $attempt->status !== 'voided' && $me->can('quiz-attempts.void')],
        ];
    }
}
