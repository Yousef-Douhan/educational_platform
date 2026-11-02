<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\CertificatePdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class CertificateTemplateController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'name', 'created_at', 'certificates_count'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:certificate-templates.view',   only: ['index', 'show', 'trashed', 'preview']),
            new Middleware('permission:certificate-templates.create', only: ['create', 'store']),
            new Middleware('permission:certificate-templates.edit',   only: ['edit', 'update', 'restore']),
            new Middleware('permission:certificate-templates.delete', only: ['destroy', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $templates = CertificateTemplate::query()
            ->withCount('certificates')
            ->search($request->get('search'))
            ->orderBy($sort, $dir)
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.certificate-templates.partials.table', compact('templates'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.certificate-templates.index', ['templates' => $templates, 'stats' => $this->stats()]);
    }

    public function create(): JsonResponse
    {
        return response()->json(['options' => new \stdClass()]);
    }

    public function store(CertificateTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $path = $request->file('design')->store('certificate-templates', 'public');

        try {
            $template = DB::transaction(function () use ($data, $path) {
                // أول قالب يصبح افتراضيًا تلقائيًا
                $makeDefault = $data['is_default'] || ! CertificateTemplate::where('is_default', true)->exists();
                $template    = CertificateTemplate::create([
                    'name'        => $data['name'],
                    'design_path' => $path,
                    'is_default'  => false,
                    'layout'      => $this->cleanLayout($data['layout'] ?? []),
                ]);

                if ($makeDefault) {
                    $template->makeDefault();
                }

                return $template;
            });
        } catch (Throwable $e) {
            report($e);
            $this->deleteFile($path);

            return response()->json(['message' => __('certificates.messages.error')], 500);
        }

        return response()->json([
            'message' => __('certificates.messages.template_created'),
            'data'    => $template->fresh(),
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(CertificateTemplate $certificateTemplate): JsonResponse
    {
        return response()->json(['data' => $certificateTemplate->loadCount('certificates')]);
    }

    public function edit(CertificateTemplate $certificateTemplate): JsonResponse
    {
        return response()->json(['data' => $certificateTemplate]);
    }

    public function update(CertificateTemplateRequest $request, CertificateTemplate $certificateTemplate): JsonResponse
    {
        $data = $request->validated();
        $old  = $certificateTemplate->design_path;
        $new  = $request->hasFile('design') ? $request->file('design')->store('certificate-templates', 'public') : null;

        try {
            DB::transaction(function () use ($data, $certificateTemplate, $new) {
                $certificateTemplate->update([
                    'name'   => $data['name'],
                    'layout' => $this->cleanLayout($data['layout'] ?? []),
                    ...($new ? ['design_path' => $new] : []),
                ]);

                if ($data['is_default']) {
                    $certificateTemplate->makeDefault();
                } elseif ($certificateTemplate->is_default) {
                    $certificateTemplate->update(['is_default' => false]);
                }
            });
        } catch (Throwable $e) {
            report($e);
            $this->deleteFile($new);

            return response()->json(['message' => __('certificates.messages.error')], 500);
        }

        if ($new) {
            $this->deleteFile($old); // بعد نجاح الحفظ فقط
        }

        // التصميم أو التخطيط تغيّر: تُبطل ملفات PDF المخزّنة لشهادات هذا القالب
        app(CertificatePdfGenerator::class)->forgetForTemplate($certificateTemplate->id);

        return response()->json([
            'message' => __('certificates.messages.template_updated'),
            'data'    => $certificateTemplate->fresh(),
            'stats'   => $this->stats(),
        ]);
    }

    /** حذف مؤقت، ولا يُحذف القالب الافتراضي */
    public function destroy(CertificateTemplate $certificateTemplate): JsonResponse
    {
        if ($certificateTemplate->is_default) {
            return response()->json(['message' => __('certificates.messages.template_is_default')], 409);
        }

        $certificateTemplate->delete();

        return response()->json([
            'message' => __('certificates.messages.template_deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $templates = CertificateTemplate::onlyTrashed()
            ->withCount('certificates')
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.certificate-templates.partials.trash-table', compact('templates'))->render(),
            'stats' => $this->stats(),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        CertificateTemplate::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => __('certificates.messages.template_restored'),
            'stats'   => $this->stats(),
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $template = CertificateTemplate::onlyTrashed()->findOrFail($id);

        // الشهادات الصادرة بهذا القالب تحتاجه عند إعادة توليد الـ PDF
        if ($template->certificates()->exists()) {
            return response()->json(['message' => __('certificates.messages.template_in_use')], 409);
        }

        $path = $template->design_path;
        $template->forceDelete();
        $this->deleteFile($path);

        return response()->json([
            'message' => __('certificates.messages.template_force_deleted'),
            'stats'   => $this->stats(),
        ]);
    }

    /** معاينة PDF بشهادة تجريبية، تعكس التخطيط المحفوظ */
    public function preview(CertificateTemplate $certificateTemplate, CertificatePdfGenerator $pdf)
    {
        abort_unless($pdf->available(), 503, __('certificates.messages.pdf_unavailable'));

        return response($pdf->renderSample($certificateTemplate), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="template-preview.pdf"',
        ]);
    }

    private function stats(): array
    {
        $total = CertificateTemplate::count();
        $used  = CertificateTemplate::has('certificates')->count();

        return [
            'total'   => $total,
            'used'    => $used,
            'unused'  => $total - $used,
            'trashed' => CertificateTemplate::onlyTrashed()->count(),
        ];
    }

    /** يحتفظ بالقيم المعروفة فقط، والحقول الفارغة تعود للقيم الافتراضية */
    private function cleanLayout(array $layout): array
    {
        $clean = [];

        foreach (['area_left', 'area_width', 'middle_top', 'qr_top'] as $key) {
            if (isset($layout[$key]) && $layout[$key] !== '') {
                $clean[$key] = (float) $layout[$key];
            }
        }

        foreach (['hide_header', 'hide_date', 'hide_signature', 'hide_qr'] as $key) {
            $clean[$key] = (bool) ($layout[$key] ?? false);
        }

        foreach (['text_color', 'accent_color'] as $key) {
            if (! empty($layout[$key])) {
                $clean[$key] = $layout[$key];
            }
        }

        return $clean;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
