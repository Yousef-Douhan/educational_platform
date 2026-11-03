<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * مراجعة تقدم الطلاب في الدورات. التقدم يُكتب من ProgressService عند دراسة الطالب،
 * فلا إنشاء ولا تعديل ولا حذف هنا؛ يمكن فقط إعادة حساب سجل عند الشك.
 * المدير يرى الكل، والمدرّس يرى طلاب دوراته.
 */
class CourseProgressController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'progress_percentage', 'finished_at', 'updated_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:progress.view', only: ['index', 'show']),
            new Middleware('permission:progress.recalculate', only: ['recalculate']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'updated_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $records = CourseProgress::query()
            ->visibleTo($user)
            ->with(['user', 'course'])
            ->search($request->get('search'))
            ->withState($request->get('state'))
            ->forCourse($request->filled('course_id') ? (int) $request->get('course_id') : null)
            ->orderBy("course_progress.{$sort}", $dir)
            ->orderBy('course_progress.id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.progress.partials.table', ['records' => $records])->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.progress.index', [
            'records' => $records,
            'stats'   => $this->stats($user),
            'courses' => Course::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    public function show(Request $request, CourseProgress $progress): JsonResponse
    {
        $this->authorizeRow($request->user(), $progress);

        return response()->json(['data' => $this->payload($progress, $request->user())]);
    }

    public function recalculate(Request $request, CourseProgress $progress, ProgressService $service): JsonResponse
    {
        $this->authorizeRow($request->user(), $progress);

        $service->recalculate($progress->user_id, $progress->course_id);

        return response()->json([
            'message' => __('progress.messages.recalculated'),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /* ---------- داخلي ---------- */

    /** المدرّس لا يرى تقدم طالب في دورة ليست له: نخفيه كأنه غير موجود */
    private function authorizeRow(User $user, CourseProgress $progress): void
    {
        abort_unless(CourseProgress::visibleTo($user)->whereKey($progress->id)->exists(), 404);
    }

    private function stats(User $user): array
    {
        $base = fn () => CourseProgress::query()->visibleTo($user);

        return [
            'total'       => $base()->count(),
            'finished'    => $base()->where('is_finished', true)->count(),
            'in_progress' => $base()->where('is_finished', false)->count(),
            'average'     => number_format((float) $base()->avg('progress_percentage'), 1),
        ];
    }

    private function payload(CourseProgress $progress, User $me): array
    {
        $progress->load(['user', 'course']);
        $tz  = config('app.timezone');
        $fmt = fn ($d) => $d?->copy()->timezone($tz)->format('Y-m-d H:i');

        $lessons = Lesson::query()
            ->where('is_published', true)
            ->whereHas('section', fn ($s) => $s->where('course_id', $progress->course_id))
            ->with('section')
            ->get()
            ->sortBy(fn (Lesson $l) => sprintf('%08d-%08d-%08d', $l->section->sort_order, $l->sort_order, $l->id))
            ->values();

        $done = LessonProgress::query()
            ->where(['user_id' => $progress->user_id, 'is_completed' => true])
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('completed_at', 'lesson_id');

        return [
            'id'          => $progress->id,
            'user'        => ['name' => $progress->user->name, 'email' => $progress->user->email, 'deleted' => $progress->user->trashed()],
            'course'      => ['title_ar' => $progress->course->title_ar, 'title_en' => $progress->course->title_en],
            'percentage'  => $progress->progress_percentage,
            'completed'   => $progress->completed_lessons_count,
            'total'       => $progress->total_lessons_count,
            'is_finished' => $progress->is_finished,
            'finished_at' => $fmt($progress->finished_at),
            'updated_at'  => $fmt($progress->updated_at),
            'enrollment'  => Enrollment::where(['user_id' => $progress->user_id, 'course_id' => $progress->course_id])->value('status'),
            'lessons'     => $lessons->map(fn (Lesson $l) => [
                'title_ar'     => $l->title_ar,
                'title_en'     => $l->title_en,
                'section_ar'   => $l->section->title_ar,
                'section_en'   => $l->section->title_en,
                'type'         => $l->content_type,
                'is_completed' => $done->has($l->id),
                'completed_at' => $fmt($done->get($l->id)),
            ])->all(),
            'can' => ['recalculate' => $me->can('progress.recalculate')],
        ];
    }
}
