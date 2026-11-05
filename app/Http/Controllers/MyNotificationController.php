<?php

namespace App\Http\Controllers;

use App\Services\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * إشعارات المستخدم الحالي فقط. كل استعلام يمر من $user->notifications()
 * فلا يمكن قراءة إشعار غيره أو تعليمه أو حذفه حتى لو عُرف معرّفه (404).
 */
class MyNotificationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $per  = min(max((int) $request->get('per_page', 15), 5), 50);

        $records = $user->notifications()
            ->when($request->get('state') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.notifications.partials.mine-list', ['records' => $records])->render(),
                'stats' => $this->stats($request),
            ]);
        }

        return view('cms.notifications.mine', ['records' => $records, 'stats' => $this->stats($request)]);
    }

    /** آخر الإشعارات للقائمة المنسدلة في الترويسة + عدّاد غير المقروءة. */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $view = NotificationPresenter::present($row);

                return [
                    'title' => (string) $view['title'],
                    'body'  => (string) $view['body'],
                    'icon'  => (string) $view['icon'],
                    'read'  => $row->read_at !== null,
                    'time'  => $row->created_at->format('Y-m-d H:i'),
                ];
            })->values();

        return response()->json([
            'items'  => $items,
            'unread' => $user->unreadNotifications()->reorder()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->own($request, $notification)->markAsRead();

        return response()->json(['message' => __('notifications.mine.marked_read'), 'stats' => $this->stats($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->reorder()->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => __('notifications.mine.marked_all'), 'stats' => $this->stats($request)]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->own($request, $notification)->delete();

        return response()->json(['message' => __('notifications.mine.deleted'), 'stats' => $this->stats($request)]);
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    private function stats(Request $request): array
    {
        $user = $request->user();

        return [
            'total'  => $user->notifications()->count(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }
}
