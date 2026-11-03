<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/** إشعارات الطالب. كل استعلام يمر من $user->notifications() فلا يصل لإشعار غيره (404) */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $records = $user->notifications()
            ->when($request->query('state') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('student.notifications.index', [
            'records' => $records,
            'unread'  => $user->unreadNotifications()->reorder()->count(),
            'onlyUnread' => $request->query('state') === 'unread',
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->own($request, $notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->reorder()->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('notifications.mine.marked_all'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->own($request, $notification)->delete();

        return back()->with('status', __('notifications.mine.deleted'));
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
