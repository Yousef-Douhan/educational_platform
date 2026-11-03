<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Notifications\PlatformNotification;
use App\Services\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/** شاشة إدارية للقراءة فقط: لا تعديل ولا حذف يدوي (التنظيف بأمر مجدول) */
class NotificationController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['created_at', 'read_at'];

    public static function middleware(): array
    {
        return [new Middleware('permission:notifications.view')];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'created_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 15), 5), 100);

        $records = UserNotification::query()
            ->with('notifiable')
            ->search($request->get('search'))
            ->ofKind($request->get('kind'))
            ->withReadState($request->get('state'))
            ->createdBetween($request->get('from'), $request->get('to'))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.notifications.partials.table', ['records' => $records])->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.notifications.index', ['records' => $records, 'stats' => $this->stats()]);
    }

    public function show(UserNotification $notification): JsonResponse
    {
        $notification->loadMissing('notifiable');
        $view = NotificationPresenter::present($notification);
        $fmt  = fn ($d) => $d?->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $data = is_array($notification->data) ? $notification->data : [];

        return response()->json(['data' => [
            'id'              => $notification->id,
            'kind'            => $view['kind'],
            'title'           => $view['title'],
            'body'            => $view['body'],
            'recipient_name'  => $notification->notifiable?->name,
            'recipient_email' => $notification->notifiable?->email,
            'is_read'         => $notification->read_at !== null,
            'read_at_label'   => $fmt($notification->read_at),
            'created_at_label' => $fmt($notification->created_at),
            // المعاملات نصوص فقط، وتُعرض في الواجهة بـ textContent
            'params'          => (object) (is_array($data['params'] ?? null) ? $data['params'] : []),
        ]]);
    }

    private function stats(): array
    {
        return [
            'total'  => UserNotification::count(),
            'unread' => UserNotification::whereNull('read_at')->count(),
            'read'   => UserNotification::whereNotNull('read_at')->count(),
            'today'  => UserNotification::where('created_at', '>=', now()->startOfDay())->count(),
        ];
    }
}
