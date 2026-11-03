<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollController extends Controller
{
    public function store(Request $request, string $slug, EnrollmentService $enrollments): RedirectResponse
    {
        $course = Course::where('slug', $slug)->where('status', 'published')->firstOrFail();

        try {
            $enrollments->enrollFree($request->user(), $course);
        } catch (EnrollmentException $e) {
            return redirect()->route('student.courses.show', $course->slug)->withErrors(['enroll' => $e->getMessage()]);
        }

        return redirect()->route('student.courses.mine')->with('status', __('student.messages.enrolled'));
    }
}
