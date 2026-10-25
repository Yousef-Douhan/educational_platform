<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('enrollments.view');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.view') && ($this->manageAll($user) || $this->isTeacher($user, $enrollment));
    }

    public function create(User $user): bool
    {
        return $user->can('enrollments.create');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.edit') && ($this->manageAll($user) || $this->isTeacher($user, $enrollment));
    }

    private function manageAll(User $user): bool
    {
        return $user->can('courses.manage-all');
    }

    private function isTeacher(User $user, Enrollment $enrollment): bool
    {
        $course = $enrollment->course;

        return $course->instructor_id === $user->id
            || $course->coInstructors()->where('users.id', $user->id)->exists();
    }
}
