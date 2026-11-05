<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCoursesController extends Controller
{
    use RespondsWithErrors;

    /** دوراتي (التسجيلات الفعّالة والمكتملة فقط) مع نسبة التقدم */
    public function index(Request $request): JsonResponse
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

        return response()->json(['data' => $enrollments->map(function (Enrollment $e) use ($progress) {
            $c = $e->course;
            $p = $progress->get($c->id);

            return [
                'course' => [
                    'id' => $c->id, 'slug' => $c->slug, 'image' => $c->image_src,
                    'title' => Loc::pick($c->title_ar, $c->title_en),
                    'status' => $c->status,
                ],
                'status'      => $e->status,
                'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                'expires_at'  => $e->expires_at?->toIso8601String(),
                'progress'    => [
                    'percent'   => (float) ($p?->progress_percentage ?? 0),
                    'completed' => (int) ($p?->completed_lessons_count ?? 0),
                    'total'     => (int) ($p?->total_lessons_count ?? 0),
                    'finished'  => (bool) ($p?->is_finished ?? false),
                ],
            ];
        })->values()]);
    }

    /** تسجيل في دورة مجانية. المدفوعة تمر من الطلبات فقط */
    public function enroll(Request $request, EnrollmentService $enrollments, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->where('status', 'published')->firstOrFail();

        try {
            $enrollments->enrollFree($request->user(), $course);
        } catch (EnrollmentException $e) {
            return $this->fail($e);
        }

        return response()->json(['message' => __('student.messages.enrolled')], 201);
    }
}
