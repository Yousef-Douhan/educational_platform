<?php

namespace App\Http\Controllers;

use App\Exceptions\CertificateException;
use App\Http\Requests\IssueCertificateRequest;
use App\Http\Requests\RevokeCertificateRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CertificateController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'issued_at', 'student_name', 'certificate_code'];

    public function __construct(private readonly CertificateService $certificates) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:certificates.view',   only: ['index', 'show', 'download']),
            new Middleware('permission:certificates.create', only: ['create', 'store', 'eligible']),
            new Middleware('permission:certificates.edit',   only: ['revoke', 'reinstate']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Certificate::class);

        $user = $request->user();
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $certificates = Certificate::query()
            ->visibleTo($user)
            ->with(['user:id,name,email,deleted_at', 'course:id,title_ar,title_en,deleted_at', 'template:id,name,deleted_at'])
            ->search($request->get('search'))
            ->when($request->get('status') === 'valid', fn($q) => $q->whereNull('revoked_at'))
            ->when($request->get('status') === 'revoked', fn($q) => $q->whereNotNull('revoked_at'))
            ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->get('course_id')))
            ->issuedBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.certificates.partials.table', compact('certificates'))->render(),
                'stats' => $this->stats($user),
            ]);
        }

        return view('cms.certificates.index', [
            'certificates' => $certificates,
            'stats'        => $this->stats($user),
            'courses'      => Course::withTrashed()->visibleTo($user)->orderBy('title_en')->get(['id', 'title_ar', 'title_en']),
        ]);
    }

    /** الدورات التي فيها طلاب مستحقون، والقوالب المتاحة */
    public function create(): JsonResponse
    {
        Gate::authorize('create', Certificate::class);

        return response()->json([
            'options' => [
                'courses' => Course::query()
                    ->whereHas('enrollments', fn($q) => $q->eligibleForCertificate())
                    ->orderBy('title_en')
                    ->get(['id', 'title_ar', 'title_en']),
                'templates' => CertificateTemplate::query()
                    ->orderByDesc('is_default')->orderBy('name')
                    ->get(['id', 'name', 'is_default']),
            ],
        ]);
    }

    /** الطلاب المستحقون في دورة: تسجيل مكتمل بلا شهادة */
    public function eligible(Request $request): JsonResponse
    {
        Gate::authorize('create', Certificate::class);

        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'search'    => ['nullable', 'string', 'max:100'],
        ]);

        $like = filled($data['search'] ?? null) ? '%' . addcslashes(trim($data['search']), '%_\\') . '%' : null;

        $rows = Enrollment::query()
            ->eligibleForCertificate()
            ->where('course_id', $data['course_id'])
            ->whereHas('user', fn($u) => $u->when($like, fn($q) => $q->where(
                fn($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
            )))
            ->with('user:id,name,email')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $rows->map(fn(Enrollment $e) => [
                'id' => $e->user->id,
                'name' => $e->user->name,
                'email' => $e->user->email,
            ])->values(),
        ]);
    }

    public function store(IssueCertificateRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $certificate = $this->certificates->issue(
                User::findOrFail($data['user_id']),
                Course::findOrFail($data['course_id']),
                isset($data['template_id']) ? CertificateTemplate::find($data['template_id']) : null,
                $request->user(),
            );
        } catch (CertificateException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['user_id' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => __('certificates.messages.issued'),
            'data'    => $this->present($certificate),
            'stats'   => $this->stats($request->user()),
        ], 201);
    }

    public function show(Certificate $certificate): JsonResponse
    {
        Gate::authorize('view', $certificate);

        return response()->json(['data' => $this->present($certificate)]);
    }

    public function revoke(RevokeCertificateRequest $request, Certificate $certificate): JsonResponse
    {
        try {
            $this->certificates->revoke($certificate, $request->validated('reason'));
        } catch (CertificateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => __('certificates.messages.revoked'),
            'data'    => $this->present($certificate->fresh()),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function reinstate(Request $request, Certificate $certificate): JsonResponse
    {
        Gate::authorize('update', $certificate);

        try {
            $this->certificates->reinstate($certificate);
        } catch (CertificateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => __('certificates.messages.reinstated'),
            'data'    => $this->present($certificate->fresh()),
            'stats'   => $this->stats($request->user()),
        ]);
    }

    public function download(Request $request, Certificate $certificate, CertificatePdfGenerator $pdf)
    {
        Gate::authorize('view', $certificate);
        abort_unless($pdf->available(), 503, __('certificates.messages.pdf_unavailable'));

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;
        $name = "certificate-{$certificate->certificate_code}" . ($lang ? "-{$lang}" : '') . '.pdf';

        // الملغاة أو أحادية اللغة: تُولَّد عند الطلب ولا تُخزَّن
        if ($certificate->is_revoked || $lang) {
            $binary = $pdf->render($certificate, $lang ?? 'both');

            return response()->streamDownload(function () use ($binary) {
                echo $binary;
            }, $name, ['Content-Type' => 'application/pdf']);
        }

        $refresh = $request->boolean('refresh') && $request->user()->can('update', $certificate);

        return Storage::disk('local')->download($pdf->path($certificate, $refresh), $name, ['Content-Type' => 'application/pdf']);
    }

    /* ---------- Helpers ---------- */

    private function present(Certificate $c): array
    {
        $c->loadMissing(['user', 'course', 'template', 'issuer']);

        return [
            'id'               => $c->id,
            'certificate_code' => $c->certificate_code,
            'status'           => $c->is_revoked ? 'revoked' : 'valid',
            'student'          => ['id' => $c->user_id, 'name' => $c->student_name, 'email' => $c->user?->email, 'deleted' => (bool) $c->user?->trashed()],
            'course'           => ['id' => $c->course_id, 'title_ar' => $c->course_title_ar, 'title_en' => $c->course_title_en, 'deleted' => (bool) $c->course?->trashed()],
            'template'         => $c->template ? ['id' => $c->template->id, 'name' => $c->template->name] : null,
            'issued_at'        => $c->issued_at?->format('Y-m-d H:i'),
            'issued_by'        => $c->issuer?->name,
            'revoked_at'       => $c->revoked_at?->format('Y-m-d H:i'),
            'revoked_reason'   => $c->revoked_reason,
            'verification_url' => route('certificates.verify', ['code' => $c->certificate_code]),
            'has_pdf'          => (bool) $c->pdf_url,
        ];
    }

    private function stats(User $user): array
    {
        $row = Certificate::query()
            ->visibleTo($user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(revoked_at IS NULL) as valid')
            ->selectRaw('SUM(revoked_at IS NOT NULL) as revoked')
            ->first();

        $thisMonth = Certificate::query()->visibleTo($user)
            ->where('issued_at', '>=', now()->startOfMonth())->count();

        return [
            'total'      => (int) $row->total,
            'valid'      => (int) $row->valid,
            'revoked'    => (int) $row->revoked,
            'this_month' => $thisMonth,
        ];
    }
}
