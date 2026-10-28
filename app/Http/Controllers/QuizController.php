<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuizController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed', 'created_at', 'questions_count'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:quizzes.view',   only: ['index', 'show', 'trashed']),
            new Middleware('permission:quizzes.create', only: ['create', 'store']),
            new Middleware('permission:quizzes.edit',   only: ['edit', 'update']),
            new Middleware('permission:quizzes.delete', only: ['destroy', 'restore', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Quiz::class);

        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $quizzes = Quiz::query()
            ->visibleTo($user)
            ->with($this->listRelations())
            ->withCount('questions')
            ->withSum('questions as total_points', 'points')
            ->search($request->get('search'))
            ->when($request->filled('course_id'), fn($q) => $q->whereHas('lesson', fn($l) => $l->withTrashed()
                ->whereHas('section', fn($s) => $s->where('course_id', $request->get('course_id')))))
            ->when($request->get('readiness') === 'ready', fn($q) => $q->has('questions'))
            ->when($request->get('readiness') === 'empty', fn($q) => $q->doesntHave('questions'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.quizzes.partials.table', compact('quizzes'))->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.quizzes.index', [
            'quizzes' => $quizzes,
            'stats'   => $this->stats($user),
            'courses' => Course::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    /** الدروس المتاحة لإنشاء اختبار: من نوع quiz، بلا اختبار (حتى المحذوف مؤقتًا)، ومن دورات يملك المستخدم تعديلها */
    public function create(Request $request): JsonResponse
    {
        Gate::authorize('create', Quiz::class);

        $lessons = Lesson::query()
            ->where('content_type', 'quiz')
            ->whereDoesntHave('quiz', fn($q) => $q->withTrashed())
            ->whereHas('section.course', fn($c) => $c->visibleTo($request->user()))
            ->with('section.course:id,title_ar,title_en')
            ->orderBy('section_id')->orderBy('sort_order')
            ->get(['id', 'section_id', 'title_ar', 'title_en']);

        return response()->json([
            'options' => [
                'lessons' => $lessons->map(fn(Lesson $l) => [
                    'id'       => $l->id,
                    'title_ar' => $l->title_ar,
                    'title_en' => $l->title_en,
                    'course'   => $l->section->course->only(['id', 'title_ar', 'title_en']),
                ])->values(),
            ],
        ]);
    }

    public function store(QuizRequest $request): JsonResponse
    {
        $quiz = Quiz::create($request->validated());

        return response()->json([
            'message' => __('quizzes.messages.created'),
            'data'    => $quiz,
            'stats'   => $this->stats($request->user()),
        ], 201);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        Gate::authorize('view', $quiz);

        return response()->json(['data' => $this->present($quiz)]);
    }

    public function edit(Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        return response()->json(['data' => $this->present($quiz)]);
    }

    public function update(QuizRequest $request, Quiz $quiz): JsonResponse
    {
        $quiz->update($request->validated());

        return response()->json([
            'message' => __('quizzes.messages.updated'),
            'data'    => $this->present($quiz->fresh()),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /** حذف مؤقت: المحاولات والأسئلة تبقى محفوظة */
    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('delete', $quiz);

        $quiz->delete();

        return response()->json([
            'message' => __('quizzes.messages.deleted'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Quiz::class);

        $quizzes = Quiz::onlyTrashed()
            ->visibleTo($request->user())
            ->with($this->listRelations())
            ->withCount('questions')
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.quizzes.partials.trash-table', compact('quizzes'))->render(),
            'stats' => $this->stats($request->user()),
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $quiz = Quiz::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $quiz);

        if ($quiz->lesson?->trashed() || $quiz->owningCourse()->trashed()) {
            return response()->json(['message' => __('quizzes.messages.parent_trashed')], 409);
        }

        $quiz->restore();

        return response()->json([
            'message' => __('quizzes.messages.restored'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $quiz = Quiz::onlyTrashed()->findOrFail($id);
        Gate::authorize('forceDelete', $quiz);

        if ($quiz->hasAttempts()) {
            return response()->json(['message' => __('quizzes.messages.has_attempts')], 409);
        }

        $quiz->forceDelete(); // الأسئلة والخيارات تُحذف بالتتالي

        return response()->json([
            'message' => __('quizzes.messages.force_deleted'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /* ---------- Helpers ---------- */

    private function listRelations(): array
    {
        return [
            'lesson:id,section_id,title_ar,title_en,deleted_at',
            'lesson.section:id,course_id',
            'lesson.section.course:id,title_ar,title_en,deleted_at',
        ];
    }

    private function present(Quiz $quiz): array
    {
        $quiz->load($this->listRelations())->loadCount('questions')->loadSum('questions as total_points', 'points');
        $course = $quiz->lesson?->section?->course;

        return [
            'id'                 => $quiz->id,
            'title_ar'           => $quiz->title_ar,
            'title_en'           => $quiz->title_en,
            'pass_mark'          => $quiz->pass_mark,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'attempts_allowed'   => $quiz->attempts_allowed,
            'questions_count'    => $quiz->questions_count,
            'total_points'       => (int) $quiz->total_points,
            'has_attempts'       => $quiz->hasAttempts(),
            'lesson' => $quiz->lesson ? ['id' => $quiz->lesson->id, 'title_ar' => $quiz->lesson->title_ar, 'title_en' => $quiz->lesson->title_en, 'deleted' => $quiz->lesson->trashed()] : null,
            'course' => $course ? ['id' => $course->id, 'title_ar' => $course->title_ar, 'title_en' => $course->title_en, 'deleted' => $course->trashed()] : null,
            'created_at' => $quiz->created_at?->format('Y-m-d H:i'),
            'updated_at' => $quiz->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function stats(User $user): array
    {
        $total = Quiz::query()->visibleTo($user)->count();
        $ready = Quiz::query()->visibleTo($user)->has('questions')->count();

        return [
            'total'   => $total,
            'ready'   => $ready,
            'empty'   => $total - $ready,
            'trashed' => Quiz::onlyTrashed()->visibleTo($user)->count(),
        ];
    }
}
