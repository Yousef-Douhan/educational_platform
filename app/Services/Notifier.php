<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * نقطة الإرسال الوحيدة. فشل الإشعار لا يجوز أن يُفشل دفعًا أو تسجيلًا أو إلغاء جلسة،
 * لذلك يُلتقط الخطأ ويُسجَّل في السجل. الإدخال داخل savepoint فلا يُفسد المعاملة الخارجية.
 */
class Notifier
{
    private const CHUNK = 500;

    public function toUser(User|int|null $user, PlatformNotification $notification): bool
    {
        try {
            $user = $user instanceof User ? $user : ($user === null ? null : User::query()->find($user));

            if ($user === null || $user->trashed()) {
                return false;
            }

            DB::transaction(fn () => Notification::sendNow($user, $notification));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** لطلاب الدورة الذين يملكون وصولًا فعليًا الآن. يعيد عدد من أُرسل لهم */
    public function toCourseStudents(int $courseId, PlatformNotification $notification): int
    {
        $sent = 0;

        try {
            User::query()
                ->whereIn('id', Enrollment::query()->grantingAccess()->where('course_id', $courseId)->select('user_id'))
                ->chunkById(self::CHUNK, function ($users) use ($notification, &$sent) {
                    DB::transaction(fn () => Notification::sendNow($users, $notification));
                    $sent += $users->count();
                });
        } catch (Throwable $e) {
            report($e);
        }

        return $sent;
    }
}
