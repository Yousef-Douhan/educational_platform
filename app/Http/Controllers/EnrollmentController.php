<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class EnrollmentController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'enrolled_at', 'expires_at', 'status'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:enrollments.view',   only: ['index', 'show']),
            new Middleware('permission:enrollments.create', only: ['create', 'store', 'lookupUsers']),
            new Middleware('permission:enrollments.edit',   only: ['edit', 'update']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Enrollment::class);

        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $enrollments = Enrollment::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'course:id,title_ar,title_en,deleted_at'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->get('course_id')))
            ->enrolledBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.enrollments.partials.table', compact('enrollments'))->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.enrollments.index', [
            'enrollments' => $enrollments,
            'stats'       => $this->stats($user),
            'courses'     => Course::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create', Enrollment::class);

        return response()->json([
            'options' => [
                'courses' => Course::query()->where('status', 'published')->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
            ],
        ]);
    }

    /** بحث عن الطلاب بالاسم أو البريد (حد أقصى 15 نتيجة) */
    public function lookupUsers(Request $request): JsonResponse
    {
        Gate::authorize('create', Enrollment::class);

        $term = trim((string) $request->get('search', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        return response()->json([
            'data' => User::query()
                ->where(fn($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        try {
            $enrollment = Enrollment::create([
                ...$request->safe()->only(['user_id', 'course_id', 'expires_at']),
                'status'      => 'active',
                'enrolled_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // سباق بين طلبين متزامنين: القيد الفريد في قاعدة البيانات هو الحَكَم
            return response()->json([
                'message' => __('enrollments.validation.already_enrolled'),
                'errors'  => ['course_id' => [__('enrollments.validation.already_enrolled')]],
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('enrollments.messages.error')], 500);
        }

        return response()->json([
            'message' => __('enrollments.messages.created'),
            'data'    => $this->present($enrollment),
            'stats'   => $this->stats($request->user()),
        ], 201);
    }

    public function show(Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('view', $enrollment);

        return response()->json(['data' => $this->present($enrollment)]);
    }

    public function edit(Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('update', $enrollment);

        return response()->json([
            'data'     => $this->present($enrollment),
            'statuses' => Enrollment::STATUSES,
        ]);
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): JsonResponse
    {
        $enrollment->update($request->validated());

        return response()->json([
            'message' => __('enrollments.messages.updated'),
            'data'    => $this->present($enrollment->fresh()),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    /* ---------- Helpers ---------- */

    private function present(Enrollment $e): array
    {
        $e->loadMissing(['user', 'course']);

        $days = $e->expires_at && $e->expires_at->isFuture()
            ? (int) ceil(now()->diffInDays($e->expires_at, false))
            : null;

        return [
            'id'               => $e->id,
            'status'           => $e->status,
            'has_access'       => $e->grantsAccess(),
            'days_left'        => $days,
            'enrolled_at'      => $e->enrolled_at?->format('Y-m-d H:i'),
            'expires_at'       => $e->expires_at?->format('Y-m-d H:i'),
            'expires_at_input' => $e->expires_at?->format('Y-m-d\TH:i'),
            'user'   => ['id' => $e->user->id, 'name' => $e->user->name, 'email' => $e->user->email, 'deleted' => $e->user->trashed()],
            'course' => [
                'id' => $e->course->id,
                'title_ar' => $e->course->title_ar,
                'title_en' => $e->course->title_en,
                'status' => $e->course->status,
                'deleted' => $e->course->trashed(),
            ],
        ];
    }

    private function stats(User $user): array
    {
        $row = Enrollment::query()
            ->visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'active') as active")
            ->selectRaw("SUM(status = 'completed') as completed")
            ->selectRaw("SUM(status = 'expired') as expired")
            ->selectRaw("SUM(status = 'cancelled') as cancelled")
            ->first();

        return [
            'total'     => (int) $row->total,
            'active'    => (int) $row->active,
            'completed' => (int) $row->completed,
            'expired'   => (int) $row->expired,
            'cancelled' => (int) $row->cancelled,
        ];
    }
}
