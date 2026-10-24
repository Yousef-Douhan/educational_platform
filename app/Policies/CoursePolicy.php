<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('courses.view');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can('courses.view') && ($this->manageAll($user) || $this->isTeacher($user, $course));
    }

    public function create(User $user): bool
    {
        return $user->can('courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('courses.edit') && ($this->manageAll($user) || $this->isTeacher($user, $course));
    }

    /** الحذف المؤقت والاسترجاع: المدير أو المدرّس الرئيسي فقط */
    public function delete(User $user, Course $course): bool
    {
        return $user->can('courses.delete')
            && ($this->manageAll($user) || $course->instructor_id === $user->id);
    }

    public function restore(User $user, Course $course): bool
    {
        return $this->delete($user, $course);
    }

    /** الحذف النهائي للمدير فقط */
    public function forceDelete(User $user, Course $course): bool
    {
        return $user->can('courses.delete') && $this->manageAll($user);
    }

    private function manageAll(User $user): bool
    {
        return $user->can('courses.manage-all');
    }

    private function isTeacher(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->id
            || $course->coInstructors()->where('users.id', $user->id)->exists();
    }
}
