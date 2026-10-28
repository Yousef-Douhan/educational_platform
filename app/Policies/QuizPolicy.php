<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quizzes.view');
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $user->can('quizzes.view') && ($this->manageAll($user) || $this->isTeacher($user, $quiz));
    }

    public function create(User $user): bool
    {
        return $user->can('quizzes.create');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->can('quizzes.edit') && ($this->manageAll($user) || $this->isTeacher($user, $quiz));
    }

    /** الحذف المؤقت والاسترجاع: المدير أو المدرّس الرئيسي للدورة */
    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->can('quizzes.delete')
            && ($this->manageAll($user) || $quiz->owningCourse()->instructor_id === $user->id);
    }

    public function restore(User $user, Quiz $quiz): bool
    {
        return $this->delete($user, $quiz);
    }

    public function forceDelete(User $user, Quiz $quiz): bool
    {
        return $user->can('quizzes.delete') && $this->manageAll($user);
    }

    private function manageAll(User $user): bool
    {
        return $user->can('courses.manage-all');
    }

    private function isTeacher(User $user, Quiz $quiz): bool
    {
        $course = $quiz->owningCourse();

        return $course->instructor_id === $user->id
            || $course->coInstructors()->where('users.id', $user->id)->exists();
    }
}
