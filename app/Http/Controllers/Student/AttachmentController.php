<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LessonAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** تنزيل مرفقات الدرس: للمسجّل في الدورة فقط (أو درس معاينة مجانية) */
class AttachmentController extends Controller
{
    public function download(Request $request, int $attachment): StreamedResponse
    {
        $file   = LessonAttachment::findOrFail($attachment);
        $lesson = $file->lesson()->with('section.course')->first();

        abort_if(! $lesson || $lesson->trashed() || ! $lesson->is_published, 404);

        $course = $lesson->section->course;
        abort_if($course->trashed() || ! in_array($course->status, ['published', 'archived'], true), 404);

        $allowed = $lesson->is_free_preview || Enrollment::grantingAccess()
            ->where(['user_id' => $request->user()->id, 'course_id' => $course->id])->exists();

        abort_unless($allowed, 403);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        $ext  = pathinfo($file->file_path, PATHINFO_EXTENSION);
        $base = trim((string) preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', $file->title)) ?: 'attachment';

        return Storage::disk('local')->download($file->file_path, $ext ? "{$base}.{$ext}" : $base);
    }
}
