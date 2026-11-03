<?php

namespace App\Services;

use App\Exceptions\LiveSessionException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;

class LiveSessionService
{
    /** الدورات المسموح بجدولة جلسات عليها: غير محذوفة وغير مؤرشفة */
    public const SCHEDULABLE_COURSE_STATUSES = ['draft', 'pending_review', 'published'];

    /**
     * إنشاء جلسة. القفل على صف المدرّس يجعل فحص التعارض آمنًا حتى لو وصل طلبان معًا
     * لدورتين مختلفتين لنفس المدرّس.
     */
    public function create(User $actor, array $data): LiveSession
    {
        return DB::transaction(function () use ($actor, $data) {
            $course = $this->lockInstructorAndLoadCourse((int) $data['course_id']);

            $session = new LiveSession($data);
            $session->forceFill(['status' => 'scheduled', 'created_by' => $actor->id]);
            $session->ends_at = $session->scheduled_at->copy()->addMinutes((int) $session->duration_minutes);

            $this->assertNoOverlap($course, $session);
            $session->save();

            return $session;
        });
    }

    public function update(LiveSession $session, array $data): LiveSession
    {
        return DB::transaction(function () use ($session, $data) {
            $course = $this->lockInstructorAndLoadCourse($session->course_id);
            $locked = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpenForChanges()) {
                throw LiveSessionException::because('not_editable');
            }

            // الدورة لا تتغير بعد الإنشاء
            unset($data['course_id']);
            $locked->fill($data);
            $locked->ends_at = $locked->scheduled_at->copy()->addMinutes((int) $locked->duration_minutes);

            $this->assertNoOverlap($course, $locked);
            $locked->save();

            // الطلاب يُبلَّغون فقط إذا تغيّر الموعد، لا عند تعديل العنوان أو الرابط
            if ($locked->wasChanged(['scheduled_at', 'duration_minutes'])) {
                app(Notifier::class)->toCourseStudents($locked->course_id, PlatformNotification::liveSessionRescheduled($locked));
            }

            return $locked;
        });
    }

    public function cancel(LiveSession $session, string $reason): LiveSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $locked = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpenForChanges()) {
                throw LiveSessionException::because('not_cancellable');
            }

            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();

            app(Notifier::class)->toCourseStudents($locked->course_id, PlatformNotification::liveSessionCancelled($locked));

            return $locked;
        });
    }

    /** الاسترجاع من السلة لا يجوز أن يخلق تعارضًا مع جلسات أخرى */
    public function restore(int $id): LiveSession
    {
        return DB::transaction(function () use ($id) {
            $trashed = LiveSession::onlyTrashed()->findOrFail($id);
            $course  = $this->lockInstructorAndLoadCourse($trashed->course_id);

            if ($trashed->status === 'scheduled' && $trashed->isOpenForChanges()) {
                $this->assertNoOverlap($course, $trashed);
            }

            $trashed->restore();

            return $trashed;
        });
    }

    /** المجدولة التي انتهى وقتها تصبح «منتهية» في قاعدة البيانات (العرض لا يحتاج هذا الأمر) */
    public function closeEnded(): int
    {
        return LiveSession::query()
            ->where('status', 'scheduled')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'ended', 'updated_at' => now()]);
    }

    /**
     * رابط الاجتماع للطالب: مسجَّل تسجيلًا فعّالًا، والجلسة غير ملغاة،
     * والوقت بين (البداية - 15 دقيقة) والنهاية. غير ذلك لا يرى الرابط أبدًا.
     */
    public function joinUrl(User $student, LiveSession $session): string
    {
        if ($student->trashed()) {
            throw LiveSessionException::because('student_inactive');
        }

        $course = Course::query()->whereKey($session->course_id)->first();

        if (! $course || $course->status !== 'published' || $session->trashed()) {
            throw LiveSessionException::because('session_unavailable');
        }

        $enrolled = Enrollment::query()->grantingAccess()
            ->where('user_id', $student->id)->where('course_id', $session->course_id)->exists();

        if (! $enrolled) {
            throw LiveSessionException::because('not_enrolled');
        }

        $now = now();

        if ($session->status === 'cancelled') {
            throw LiveSessionException::because('session_cancelled');
        }
        if ($session->status === 'ended' || $session->ends_at->lte($now)) {
            throw LiveSessionException::because('session_ended');
        }
        if ($session->scheduled_at->copy()->subMinutes(LiveSession::JOIN_EARLY_MINUTES)->gt($now)) {
            throw LiveSessionException::because('not_started');
        }

        return $session->meeting_url;
    }

    /* ---------- داخلي ---------- */

    private function lockInstructorAndLoadCourse(int $courseId, bool $forUpdate = true): Course
    {
        $course = Course::query()->find($courseId);

        if (! $course || ! in_array($course->status, self::SCHEDULABLE_COURSE_STATUSES, true)) {
            throw LiveSessionException::because('course_unavailable');
        }

        // ترتيب القفل ثابت: صف المدرّس أولًا ثم الجلسة
        if ($forUpdate) {
            User::withTrashed()->whereKey($course->instructor_id)->lockForUpdate()->first();
        }

        return $course;
    }

    private function assertNoOverlap(Course $course, LiveSession $session): void
    {
        $exists = LiveSession::query()
            ->overlapping($course, $session->scheduled_at, $session->ends_at, $session->exists ? $session->id : null)
            ->exists();

        if ($exists) {
            throw LiveSessionException::because('overlap');
        }
    }
}
