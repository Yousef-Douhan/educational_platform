<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/** قراءة فقط: لا مسارات إنشاء أو تعديل أو حذف للسجل */
class AuditLogController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'created_at', 'action'];

    public static function middleware(): array
    {
        return [new Middleware('permission:audit-logs.view')];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'created_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 15), 5), 100);

        $records = AuditLog::query()
            ->search($request->get('search'))
            ->ofType($request->get('type'))
            ->ofEvent($request->get('event'))
            ->between($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.audit-logs.partials.table', ['records' => $records])->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.audit-logs.index', ['records' => $records, 'stats' => $this->stats()]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $fmt = fn ($d, string $f) => $d?->copy()->timezone(config('app.timezone'))->format($f);

        return response()->json(['data' => [
            'id'             => $auditLog->id,
            'action'         => $auditLog->action,
            'type'           => $auditLog->subject_type,
            'event'          => $auditLog->event,
            'user_name'      => $auditLog->user_name,
            'user_email'     => $auditLog->user_email,
            'subject_id'     => $auditLog->subject_id,
            'subject_label'  => $auditLog->subject_label,
            'description'    => $auditLog->description,
            'ip_address'     => $auditLog->ip_address,
            'user_agent'     => $auditLog->user_agent,
            'old_values'     => $auditLog->old_values ?? new \stdClass(),
            'new_values'     => $auditLog->new_values ?? new \stdClass(),
            'created_at_label' => $fmt($auditLog->created_at, 'Y-m-d H:i:s'),
        ]]);
    }

    private function stats(): array
    {
        return [
            'total'         => AuditLog::count(),
            'today'         => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
            'week'          => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            'deletions'     => AuditLog::whereIn('event', ['deleted', 'force_deleted'])->count(),
            'failed_logins' => AuditLog::where('event', 'login_failed')->count(),
        ];
    }
}
