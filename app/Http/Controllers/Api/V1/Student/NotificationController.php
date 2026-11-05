<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** إشعارات الطالب. كل استعلام يمر من $user->notifications() فلا يصل لإشعار غيره (404). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $records = $user->notifications()
            ->when($request->query('state') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->reorder()->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return response()->json([
            'data' => $records->getCollection()->map(function (DatabaseNotification $n) {
                $p = NotificationPresenter::present($n, app()->getLocale());

                return [
                    'id' => $n->id, 'kind' => $p['kind'], 'title' => $p['title'], 'body' => $p['body'],
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $records->currentPage(), 'last_page' => $records->lastPage(),
                'total' => $records->total(),
                'unread' => $user->unreadNotifications()->reorder()->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->own($request, $notification)->markAsRead();

        return response()->json(['message' => 'ok']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->reorder()->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => __('notifications.mine.marked_all')]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->own($request, $notification)->delete();

        return response()->json(['message' => __('notifications.mine.deleted')]);
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
