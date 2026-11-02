<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('certificates.view');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $user->can('certificates.view') && ($this->manageAll($user) || $this->isTeacher($user, $certificate));
    }

    public function create(User $user): bool
    {
        return $user->can('certificates.create');
    }

    /** الإلغاء وإعادة التفعيل: للمدير فقط */
    public function update(User $user, Certificate $certificate): bool
    {
        return $user->can('certificates.edit') && $this->manageAll($user);
    }

    private function manageAll(User $user): bool
    {
        return $user->can('courses.manage-all');
    }

    private function isTeacher(User $user, Certificate $certificate): bool
    {
        $course = $certificate->course;

        return $course->instructor_id === $user->id
            || $course->coInstructors()->where('users.id', $user->id)->exists();
    }
}
