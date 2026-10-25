<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonAttachmentRequest;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LessonAttachmentController extends Controller implements HasMiddleware
{
    private const MAX_PER_LESSON = 20;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:courses.view', only: ['download']),
            new Middleware('permission:courses.edit', only: ['store', 'destroy']),
        ];
    }

    public function store(LessonAttachmentRequest $request, Lesson $lesson): JsonResponse
    {
        if ($lesson->attachments()->count() >= self::MAX_PER_LESSON) {
            return response()->json(['message' => __('curriculum.messages.attachment_limit')], 409);
        }

        $file = $request->file('file');
        $size = $file->getSize();
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // قرص خاص وبأسماء عشوائية: لا يُستخدم اسم الملف الأصلي في المسار
        $path = $file->store("lesson-attachments/{$lesson->id}", 'local');

        try {
            $attachment = $lesson->attachments()->create([
                'title'     => $request->validated('title') ?: (Str::limit($name, 200, '') ?: 'attachment'),
                'file_path' => $path,
                'file_size' => $size,
            ]);
        } catch (Throwable $e) {
            report($e);
            Storage::disk('local')->delete($path);

            return response()->json(['message' => __('curriculum.messages.error')], 500);
        }

        return response()->json([
            'message' => __('curriculum.messages.attachment_uploaded'),
            'data'    => [
                'id'           => $attachment->id,
                'title'        => $attachment->title,
                'file_size'    => $attachment->file_size,
                'download_url' => route('cms.admin.attachments.download', $attachment),
            ],
        ], 201);
    }

    public function destroy(LessonAttachment $attachment): JsonResponse
    {
        $course = $attachment->lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('update', $course);

        $path = $attachment->file_path;
        $attachment->delete();
        Storage::disk('local')->delete($path);

        return response()->json(['message' => __('curriculum.messages.attachment_deleted')]);
    }

    public function download(LessonAttachment $attachment): StreamedResponse
    {
        $course = $attachment->lesson->owningCourse();
        abort_if($course->trashed(), 404);
        Gate::authorize('view', $course);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $ext  = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
        $name = (Str::slug($attachment->title) ?: 'attachment') . ($ext ? ".{$ext}" : '');

        return Storage::disk('local')->download($attachment->file_path, $name);
    }
}
