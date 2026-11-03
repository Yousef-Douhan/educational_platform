<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyCoursesController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $enrollments = Enrollment::query()->grantingAccess()
            ->where('user_id', $user->id)
            ->whereHas('course')
            ->with('course')
            ->orderByDesc('enrolled_at')
            ->get();

        $progress = CourseProgress::where('user_id', $user->id)
            ->whereIn('course_id', $enrollments->pluck('course_id'))->get()->keyBy('course_id');

        return view('student.courses.mine', ['enrollments' => $enrollments, 'progress' => $progress]);
    }
}
