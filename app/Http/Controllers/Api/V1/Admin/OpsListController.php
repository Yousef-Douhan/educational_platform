<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Support\PagedJson;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UserNotification;
use App\Services\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * قوائم التشغيل للإدارة (تسجيلات، شهادات، قوالب، جلسات، اختبارات، محاولات، تقدّم، إشعارات، تدقيق).
 * النطاق visibleTo يقيّد غير المدير بدوراته (نفس الـCMS). الصلاحيات على المسارات + السياسات حيث وُجدت.
 */
class OpsListController extends Controller
{
    use PagedJson;

    public function enrollments(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Enrollment::class);
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'enrolled_at', 'expires_at', 'status'], 'id');

        $rows = Enrollment::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'course:id,title_ar,title_en,deleted_at'])
            ->search($this->str($request, 'search'))
            ->when($this->str($request, 'status'), fn ($q, $s) => $q->where('status', $s))
            ->when($this->int($request, 'course_id'), fn ($q, $c) => $q->where('course_id', $c))
            ->enrolledBetween($this->str($request, 'from'), $this->str($request, 'to'))
            ->orderBy($col, $dir)
            ->paginate($this->per($request))
            ->withQueryString();

        $s = Enrollment::query()->visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'active') as active")
            ->selectRaw("SUM(status = 'completed') as completed")
            ->selectRaw("SUM(status = 'expired') as expired")
            ->selectRaw("SUM(status = 'cancelled') as cancelled")
            ->first();

        return $this->page($rows, fn (Enrollment $e) => [
            'id'          => $e->id,
            'status'      => $e->status,
            'has_access'  => (bool) $e->has_access,
            'user'        => $this->who($e->user),
            'course'      => $this->courseRef($e->course),
            'enrolled_at' => $this->iso($e->enrolled_at),
            'expires_at'  => $this->iso($e->expires_at),
        ], ['stats' => [
            'total' => (int) $s->total, 'active' => (int) $s->active, 'completed' => (int) $s->completed,
            'expired' => (int) $s->expired, 'cancelled' => (int) $s->cancelled,
        ]]);
    }

    public function certificates(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Certificate::class);
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'issued_at', 'student_name', 'certificate_code'], 'id');
        $status = $this->str($request, 'status');

        $rows = Certificate::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'course:id,title_ar,title_en,deleted_at', 'template:id,name,deleted_at'])
            ->search($this->str($request, 'search'))
            ->when($status === 'valid', fn ($q) => $q->whereNull('revoked_at'))
            ->when($status === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->when($this->int($request, 'course_id'), fn ($q, $c) => $q->where('course_id', $c))
            ->issuedBetween($this->str($request, 'from'), $this->str($request, 'to'))
            ->orderBy($col, $dir)
            ->paginate($this->per($request))
            ->withQueryString();

        $s = Certificate::query()->visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(revoked_at IS NULL) as valid')
            ->selectRaw('SUM(revoked_at IS NOT NULL) as revoked')
            ->first();

        return $this->page($rows, fn (Certificate $c) => [
            'id'               => $c->id,
            'certificate_code' => $c->certificate_code,
            'student_name'     => $c->student_name,
            'user'             => $this->who($c->user),
            'course'           => ['id' => $c->course_id, 'title_ar' => $c->course_title_ar, 'title_en' => $c->course_title_en],
            'template'         => $c->template ? ['id' => $c->template->id, 'name' => $c->template->name] : null,
            'issued_at'        => $this->iso($c->issued_at),
            'revoked_at'       => $this->iso($c->revoked_at),
            'is_valid'         => $c->revoked_at === null,
        ], ['stats' => [
            'total' => (int) $s->total, 'valid' => (int) $s->valid, 'revoked' => (int) $s->revoked,
            'this_month' => Certificate::query()->visibleTo($user)->where('issued_at', '>=', now()->startOfMonth())->count(),
        ]]);
    }

    public function certificateTemplates(Request $request): JsonResponse
    {
        [$col, $dir] = $this->sort($request, ['id', 'name', 'created_at', 'certificates_count'], 'id');

        $rows = CertificateTemplate::query()
            ->withCount('certificates')
            ->search($this->str($request, 'search'))
            ->orderBy($col, $dir)
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (CertificateTemplate $t) => $this->template($t),
            ['stats' => ['total' => CertificateTemplate::count(), 'trashed' => CertificateTemplate::onlyTrashed()->count()]]);
    }

    public function trashedCertificateTemplates(Request $request): JsonResponse
    {
        $rows = CertificateTemplate::onlyTrashed()
            ->withCount('certificates')
            ->search($this->str($request, 'search'))
            ->latest('deleted_at')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (CertificateTemplate $t) => $this->template($t) + ['deleted_at' => $this->iso($t->deleted_at)]);
    }

    public function liveSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'scheduled_at', 'duration_minutes', 'created_at'], 'scheduled_at');

        $rows = LiveSession::query()
            ->visibleTo($user)
            ->with('course:id,title_ar,title_en,deleted_at')
            ->search($this->str($request, 'search'))
            ->withPhase($this->str($request, 'phase'))
            ->forCourse($this->int($request, 'course_id'))
            ->orderBy($col, $dir)
            ->orderByDesc('id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (LiveSession $s) => $this->session($s));
    }

    public function trashedLiveSessions(Request $request): JsonResponse
    {
        $rows = LiveSession::onlyTrashed()
            ->visibleTo($request->user())
            ->with('course:id,title_ar,title_en,deleted_at')
            ->search($this->str($request, 'search'))
            ->latest('deleted_at')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (LiveSession $s) => $this->session($s) + ['deleted_at' => $this->iso($s->deleted_at)]);
    }

    public function quizzes(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Quiz::class);
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed', 'created_at', 'questions_count'], 'id');
        $ready = $this->str($request, 'readiness');

        $rows = Quiz::query()
            ->visibleTo($user)
            ->with($this->quizRelations())
            ->withCount('questions')
            ->withSum('questions as total_points', 'points')
            ->search($this->str($request, 'search'))
            ->when($this->int($request, 'course_id'), fn ($q, $c) => $q->whereHas('lesson', fn ($l) => $l->withTrashed()
                ->whereHas('section', fn ($s) => $s->where('course_id', $c))))
            ->when($ready === 'ready', fn ($q) => $q->has('questions'))
            ->when($ready === 'empty', fn ($q) => $q->doesntHave('questions'))
            ->orderBy($col, $dir)
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Quiz $q) => $this->quiz($q), ['stats' => [
            'total'   => Quiz::query()->visibleTo($user)->count(),
            'ready'   => Quiz::query()->visibleTo($user)->has('questions')->count(),
            'trashed' => Quiz::onlyTrashed()->visibleTo($user)->count(),
        ]]);
    }

    public function trashedQuizzes(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Quiz::class);

        $rows = Quiz::onlyTrashed()
            ->visibleTo($request->user())
            ->with($this->quizRelations())
            ->withCount('questions')
            ->search($this->str($request, 'search'))
            ->latest('deleted_at')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Quiz $q) => $this->quiz($q) + ['deleted_at' => $this->iso($q->deleted_at)]);
    }

    public function quizAttempts(Request $request): JsonResponse
    {
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'score', 'status', 'started_at'], 'started_at');

        $rows = QuizAttempt::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'quiz:id,title_ar,title_en,deleted_at'])
            ->search($this->str($request, 'search'))
            ->withStatus($this->str($request, 'status'))
            ->withResult($this->str($request, 'result'))
            ->forQuiz($this->int($request, 'quiz_id'))
            ->startedBetween($this->date($request, 'from'), $this->date($request, 'to'))
            ->orderBy("quiz_attempts.{$col}", $dir)
            ->orderByDesc('quiz_attempts.id')
            ->paginate($this->per($request))
            ->withQueryString();

        $base   = fn () => QuizAttempt::query()->visibleTo($user);
        $graded = fn () => $base()->whereIn('status', ['submitted', 'expired']);

        return $this->page($rows, fn (QuizAttempt $a) => [
            'id'           => $a->id,
            'status'       => $a->status,
            'score'        => $a->score,
            'is_passed'    => $a->is_passed,
            'user'         => $this->who($a->user),
            'quiz'         => $a->quiz ? ['id' => $a->quiz->id, 'title_ar' => $a->quiz->title_ar, 'title_en' => $a->quiz->title_en] : null,
            'started_at'   => $this->iso($a->started_at),
            'submitted_at' => $this->iso($a->submitted_at),
            'voided_at'    => $this->iso($a->voided_at),
        ], ['stats' => [
            'total'       => $base()->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'passed'      => $graded()->where('is_passed', true)->count(),
            'failed'      => $graded()->where('is_passed', false)->count(),
            'average'     => number_format((float) $graded()->avg('score'), 1, '.', ''),
        ]]);
    }

    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();
        [$col, $dir] = $this->sort($request, ['id', 'progress_percentage', 'finished_at', 'updated_at'], 'updated_at');

        $rows = CourseProgress::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'course:id,title_ar,title_en,deleted_at'])
            ->search($this->str($request, 'search'))
            ->withState($this->str($request, 'state'))
            ->forCourse($this->int($request, 'course_id'))
            ->orderBy("course_progress.{$col}", $dir)
            ->orderByDesc('course_progress.id')
            ->paginate($this->per($request))
            ->withQueryString();

        $base = fn () => CourseProgress::query()->visibleTo($user);

        return $this->page($rows, fn (CourseProgress $p) => [
            'id'                  => $p->id,
            'user'                => $this->who($p->user),
            'course'              => $this->courseRef($p->course),
            'completed_lessons'   => $p->completed_lessons_count,
            'total_lessons'       => $p->total_lessons_count,
            'progress_percentage' => $p->progress_percentage,
            'is_finished'         => $p->is_finished,
            'finished_at'         => $this->iso($p->finished_at),
            'updated_at'          => $this->iso($p->updated_at),
        ], ['stats' => [
            'total'       => $base()->count(),
            'finished'    => $base()->where('is_finished', true)->count(),
            'in_progress' => $base()->where('is_finished', false)->count(),
            'average'     => number_format((float) $base()->avg('progress_percentage'), 1, '.', ''),
        ]]);
    }

    public function notifications(Request $request): JsonResponse
    {
        [$col, $dir] = $this->sort($request, ['created_at', 'read_at'], 'created_at');

        $rows = UserNotification::query()
            ->with('notifiable')
            ->search($this->str($request, 'search'))
            ->ofKind($this->str($request, 'kind'))
            ->withReadState($this->str($request, 'state'))
            ->createdBetween($this->str($request, 'from'), $this->str($request, 'to'))
            ->orderBy($col, $dir)
            ->orderByDesc('id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, function (UserNotification $n) {
            $v = NotificationPresenter::present($n);

            return [
                'id'         => $n->id,
                'kind'       => $v['kind'],
                'title'      => $v['title'],
                'body'       => $v['body'],
                'recipient'  => $n->notifiable ? ['name' => $n->notifiable->name, 'email' => $n->notifiable->email] : null,
                'is_read'    => $n->read_at !== null,
                'read_at'    => $this->iso($n->read_at),
                'created_at' => $this->iso($n->created_at),
            ];
        }, ['stats' => [
            'total'  => UserNotification::count(),
            'unread' => UserNotification::whereNull('read_at')->count(),
            'read'   => UserNotification::whereNotNull('read_at')->count(),
            'today'  => UserNotification::where('created_at', '>=', now()->startOfDay())->count(),
        ]]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        [$col, $dir] = $this->sort($request, ['id', 'created_at', 'action'], 'created_at');

        $rows = AuditLog::query()
            ->search($this->str($request, 'search'))
            ->ofType($this->str($request, 'type'))
            ->ofEvent($this->str($request, 'event'))
            ->between($this->str($request, 'from'), $this->str($request, 'to'))
            ->orderBy($col, $dir)
            ->orderByDesc('id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (AuditLog $l) => [
            'id'            => $l->id,
            'event'         => $l->event,
            'subject_type'  => $l->subject_type,
            'subject_id'    => $l->subject_id,
            'subject_label' => $l->subject_label,
            'description'   => $l->description,
            'user_name'     => $l->user_name,
            'user_email'    => $l->user_email,
            'ip_address'    => $l->ip_address,
            'created_at'    => $this->iso($l->created_at),
        ], ['stats' => [
            'total'         => AuditLog::count(),
            'today'         => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
            'week'          => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            'deletions'     => AuditLog::whereIn('event', ['deleted', 'force_deleted'])->count(),
            'failed_logins' => AuditLog::where('event', 'login_failed')->count(),
        ]]);
    }

    /* ---------- تحويل الصفوف ---------- */

    private function template(CertificateTemplate $t): array
    {
        return [
            'id'                 => $t->id,
            'name'               => $t->name,
            'is_default'         => (bool) $t->is_default,
            'design_url'         => $t->design_url,
            'certificates_count' => (int) $t->certificates_count,
            'created_at'         => $this->iso($t->created_at),
        ];
    }

    // رابط الاجتماع لا يظهر في القوائم؛ يُعرض في show فقط للمخوَّلين
    private function session(LiveSession $s): array
    {
        return [
            'id'               => $s->id,
            'title_ar'         => $s->title_ar,
            'title_en'         => $s->title_en,
            'phase'            => $s->phase(),
            'status'           => $s->status,
            'course'           => $this->courseRef($s->course),
            'scheduled_at'     => $this->iso($s->scheduled_at),
            'ends_at'          => $this->iso($s->ends_at),
            'duration_minutes' => $s->duration_minutes,
        ];
    }

    private function quiz(Quiz $q): array
    {
        $course = $q->lesson?->section?->course;

        return [
            'id'                 => $q->id,
            'title_ar'           => $q->title_ar,
            'title_en'           => $q->title_en,
            'pass_mark'          => $q->pass_mark,
            'time_limit_minutes' => $q->time_limit_minutes,
            'attempts_allowed'   => $q->attempts_allowed,
            'questions_count'    => (int) $q->questions_count,
            'total_points'       => $q->total_points,
            'lesson'             => $q->lesson ? ['id' => $q->lesson->id, 'title_ar' => $q->lesson->title_ar, 'title_en' => $q->lesson->title_en] : null,
            'course'             => $this->courseRef($course),
        ];
    }

    private function quizRelations(): array
    {
        return [
            'lesson:id,section_id,title_ar,title_en,deleted_at',
            'lesson.section:id,course_id',
            'lesson.section.course:id,title_ar,title_en,deleted_at',
        ];
    }
}
