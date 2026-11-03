<?php

namespace App\Http\Controllers;

use App\Exceptions\LiveSessionException;
use App\Http\Requests\LiveSessionCancelRequest;
use App\Http\Requests\LiveSessionRequest;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class LiveSessionController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'scheduled_at', 'duration_minutes', 'created_at'];

    public function __construct(private readonly LiveSessionService $sessions)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:live-sessions.view',   only: ['index', 'show', 'trashed']),
            new Middleware('permission:live-sessions.create', only: ['create', 'store']),
            new Middleware('permission:live-sessions.edit',   only: ['edit', 'update', 'cancel', 'restore']),
            new Middleware('permission:live-sessions.delete', only: ['destroy', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'scheduled_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $records = LiveSession::query()
            ->visibleTo($user)
            ->with('course:id,title_ar,title_en,deleted_at')
            ->search($request->get('search'))
            ->withPhase($request->get('phase'))
            ->forCourse($request->filled('course_id') ? (int) $request->get('course_id') : null)
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.live-sessions.partials.table', ['records' => $records])->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.live-sessions.index', [
            'records' => $records,
            'stats'   => $this->stats($user),
            'courses' => Course::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        return response()->json([
            'options' => [
                'courses' => Course::query()->visibleTo($request->user())
                    ->whereIn('status', LiveSessionService::SCHEDULABLE_COURSE_STATUSES)
                    ->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
            ],
        ]);
    }

    public function store(LiveSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->sessions->create($request->user(), $request->validated());
        } catch (LiveSessionException $e) {
            return $this->failure($e);
        }

        return response()->json([
            'message' => __('live_sessions.messages.created'),
            'data'    => $this->payload($session->load('course')),
            'stats'   => $this->stats($request->user()),
        ], 201);
    }

    public function show(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request->user(), $liveSession);

        return response()->json(['data' => $this->payload($liveSession->load('course'))]);
    }

    public function edit(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request->user(), $liveSession);

        return response()->json(['data' => $this->payload($liveSession->load('course'))]);
    }

    public function update(LiveSessionRequest $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request->user(), $liveSession);

        try {
            $session = $this->sessions->update($liveSession, $request->validated());
        } catch (LiveSessionException $e) {
            return $this->failure($e);
        }

        return response()->json([
            'message' => __('live_sessions.messages.updated'),
            'data'    => $this->payload($session->load('course')),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function cancel(LiveSessionCancelRequest $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request->user(), $liveSession);

        try {
            $session = $this->sessions->cancel($liveSession, $request->validated('reason'));
        } catch (LiveSessionException $e) {
            return $this->failure($e);
        }

        return response()->json([
            'message' => __('live_sessions.messages.cancelled'),
            'data'    => $this->payload($session->load('course')),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /** حذف مؤقت: يختفي الرابط عن الطلاب فورًا ويمكن الاسترجاع */
    public function destroy(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request->user(), $liveSession);
        $liveSession->delete();

        return response()->json(['message' => __('live_sessions.messages.deleted'), 'stats' => $this->stats($request->user())]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $records = LiveSession::onlyTrashed()
            ->visibleTo($request->user())
            ->with('course:id,title_ar,title_en,deleted_at')
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.live-sessions.partials.trash-table', ['records' => $records])->render(),
            'stats' => $this->stats($request->user()),
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->trashedVisible($request->user(), $id);

        try {
            $this->sessions->restore($id);
        } catch (LiveSessionException $e) {
            return $this->failure($e);
        }

        return response()->json(['message' => __('live_sessions.messages.restored'), 'stats' => $this->stats($request->user())]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->trashedVisible($request->user(), $id)->forceDelete();

        return response()->json(['message' => __('live_sessions.messages.force_deleted'), 'stats' => $this->stats($request->user())]);
    }

    /* ---------- داخلي ---------- */

    /** جلسة خارج نطاق المدرّس تبدو غير موجودة تمامًا */
    private function authorizeSession(User $user, LiveSession $session): void
    {
        abort_unless(LiveSession::query()->visibleTo($user)->whereKey($session->id)->exists(), 404);
    }

    private function trashedVisible(User $user, int $id): LiveSession
    {
        return LiveSession::onlyTrashed()->visibleTo($user)->findOrFail($id);
    }

    private function failure(LiveSessionException $e): JsonResponse
    {
        $field = in_array($e->reason, ['overlap'], true) ? 'scheduled_at' : null;

        return response()->json(array_filter([
            'message' => $e->getMessage(),
            'errors'  => $field ? [$field => [$e->getMessage()]] : null,
        ]), $field ? 422 : 409);
    }

    private function stats(User $user): array
    {
        $base = fn () => LiveSession::query()->visibleTo($user);

        return [
            'total'     => $base()->count(),
            'upcoming'  => $base()->withPhase('upcoming')->count(),
            'live'      => $base()->withPhase('live')->count(),
            'ended'     => $base()->withPhase('ended')->count(),
            'cancelled' => $base()->withPhase('cancelled')->count(),
            'trashed'   => LiveSession::onlyTrashed()->visibleTo($user)->count(),
        ];
    }

    /** مواعيد بتوقيت التطبيق حتى لا يتزحزح الوقت في حقول datetime-local */
    private function payload(LiveSession $s): array
    {
        $fmt = fn ($d, string $f) => $d?->copy()->timezone(config('app.timezone'))->format($f);

        return [
            'id'                 => $s->id,
            'course_id'          => $s->course_id,
            'course_title_ar'    => $s->course?->title_ar,
            'course_title_en'    => $s->course?->title_en,
            'title_ar'           => $s->title_ar,
            'title_en'           => $s->title_en,
            'meeting_url'        => $s->meeting_url,
            'duration_minutes'   => $s->duration_minutes,
            'phase'              => $s->phase(),
            'is_open'            => $s->isOpenForChanges(),
            'cancel_reason'      => $s->cancel_reason,
            'scheduled_at_input' => $fmt($s->scheduled_at, 'Y-m-d\TH:i'),
            'scheduled_at_label' => $fmt($s->scheduled_at, 'Y-m-d H:i'),
            'ends_at_label'      => $fmt($s->ends_at, 'Y-m-d H:i'),
            'cancelled_at_label' => $fmt($s->cancelled_at, 'Y-m-d H:i'),
            'created_at_label'   => $fmt($s->created_at, 'Y-m-d H:i'),
            'updated_at_label'   => $fmt($s->updated_at, 'Y-m-d H:i'),
        ];
    }
}
