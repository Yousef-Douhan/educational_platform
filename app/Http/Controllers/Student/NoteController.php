<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentBoardPage;
use App\Models\StudentNote;
use App\Models\User;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** ملاحظات الطالب: مجلداتها دوراته المسجَّل بها + مجلد "عام". كل شيء مقيَّد بصاحبه. */
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.notes.index', $this->data($request));
    }

    /** نفس الأداة داخل لوحة التحكم للموظفين (الملاحظات شخصية أيضًا) */
    public function cmsIndex(Request $request): View
    {
        return view('cms.notes.index', $this->data($request));
    }

    /** @return array{courses:mixed,notes:mixed,pages:mixed} */
    private function data(Request $request): array
    {
        $user = $request->user();

        // دورات التسجيل غير الملغى، مع أي دورة لديه فيها ملاحظات (حتى لو انتهى وصوله)
        $courseIds = Enrollment::where('user_id', $user->id)->where('status', '!=', 'cancelled')->pluck('course_id')
            ->merge(StudentNote::where('user_id', $user->id)->whereNotNull('course_id')->pluck('course_id'));

        // الموظف: أحدث 30 دورة ضمن نطاقه (المدير كل الدورات، والمدرّس دوراته) كي لا تطول القائمة
        if ($user->isStaff()) {
            $courseIds = $courseIds->merge(Course::query()->visibleTo($user)->latest('id')->limit(30)->pluck('courses.id'));
        }

        $courses = Course::withTrashed()->whereIn('id', $courseIds->unique()->values())->get(['id', 'title_ar', 'title_en'])
            ->map(fn ($c) => ['id' => $c->id, 'title' => Loc::pick($c->title_ar, $c->title_en)])
            ->sortBy('title')->values();

        $notes = StudentNote::where('user_id', $user->id)->orderByDesc('updated_at')->get()
            ->map->toListItem()->values();

        $pages = StudentBoardPage::where('user_id', $user->id)->orderBy('position')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();

        return ['courses' => $courses, 'notes' => $notes, 'pages' => $pages];
    }

    /** مسموح الحفظ تحت دورة: مسجَّل بها (غير ملغاة)، أو الموظف إن كانت ضمن نطاقه */
    private function canFile(User $user, int $courseId): bool
    {
        if (Enrollment::where('user_id', $user->id)->where('course_id', $courseId)->where('status', '!=', 'cancelled')->exists()) {
            return true;
        }

        return $user->isStaff() && Course::query()->visibleTo($user)->where('courses.id', $courseId)->exists();
    }

    public function show(Request $request, StudentNote $note): JsonResponse
    {
        $this->own($request, $note);

        return response()->json(['id' => $note->id, 'course_id' => $note->course_id, 'title' => $note->title,
            'body' => (string) $note->body, 'updated_at' => $note->updated_at?->toIso8601String()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:150'],
            'course_id' => ['nullable', 'integer'],
        ]);

        // المجلد = دورة مسجَّل بها الطالب (أو ضمن نطاق الموظف). نتحقق صراحةً بدل قاعدة exists
        if (($data['course_id'] ?? null) !== null && ! $this->canFile($user, (int) $data['course_id'])) {
            throw ValidationException::withMessages(['course_id' => __('validation.exists', ['attribute' => 'course_id'])]);
        }

        if (StudentNote::where('user_id', $user->id)->count() >= StudentNote::MAX_PER_USER) {
            return response()->json(['message' => __('student.notes.limit')], 422);
        }

        $note = StudentNote::create(['user_id' => $user->id, 'course_id' => $data['course_id'] ?? null, 'title' => trim($data['title']), 'body' => '']);

        return response()->json($note->toListItem(), 201);
    }

    public function update(Request $request, StudentNote $note): JsonResponse
    {
        $this->own($request, $note);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body'  => ['nullable', 'string', 'max:' . StudentNote::MAX_BODY],
        ]);

        $note->update(['title' => trim($data['title']), 'body' => $data['body'] ?? '']);

        return response()->json($note->fresh()->toListItem());
    }

    public function destroy(Request $request, StudentNote $note): JsonResponse
    {
        $this->own($request, $note);
        $note->delete();

        return response()->json(['ok' => true]);
    }

    /** ملاحظة غيرك = 404 كي لا نكشف وجودها */
    private function own(Request $request, StudentNote $note): void
    {
        abort_unless($note->user_id === $request->user()->id, 404);
    }
}
