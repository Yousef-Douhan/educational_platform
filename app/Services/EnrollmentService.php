<?php

namespace App\Services;

use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** التسجيل المجاني الذاتي. المدفوع يمر من الطلبات فقط (OrderService) */
class EnrollmentService
{
    public function enrollFree(User $user, Course $course): Enrollment
    {
        if (($user->status ?? 'active') !== 'active') {
            throw EnrollmentException::because('account_inactive');
        }

        if ($course->trashed() || $course->status !== 'published') {
            throw EnrollmentException::because('course_unavailable');
        }

        if (! $course->is_free) {
            throw EnrollmentException::because('not_free');
        }

        if ($course->instructor_id === $user->id || $course->coInstructors()->where('users.id', $user->id)->exists()) {
            throw EnrollmentException::because('own_course');
        }

        $enrollment = DB::transaction(function () use ($user, $course) {
            $keys     = ['user_id' => $user->id, 'course_id' => $course->id];
            $existing = Enrollment::where($keys)->lockForUpdate()->first();

            if ($existing === null) {
                try {
                    return Enrollment::create($keys + ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]);
                } catch (UniqueConstraintViolationException) {
                    throw EnrollmentException::because('already_enrolled');
                }
            }

            if ($existing->grantsAccess()) {
                throw EnrollmentException::because('already_enrolled');
            }

            // الملغى قرار إداري (أو استرداد): لا يعيده الطالب بنفسه. المنتهي فقط يُجدَّد
            if (! in_array($existing->status, ['expired', 'active'], true)) {
                throw EnrollmentException::because('enrollment_closed');
            }

            $existing->update(['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]);

            return $existing;
        });

        app(Notifier::class)->toUser($user, PlatformNotification::enrollmentActivated($course));

        return $enrollment;
    }
}
