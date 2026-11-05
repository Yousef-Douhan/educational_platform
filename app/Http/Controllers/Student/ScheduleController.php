<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentScheduleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** الجدول الأسبوعي الشخصي (للطالب وللموظف). كل شيء مقيَّد بصاحبه؛ التعارض يُكشف في الواجهة ويُسمح به مع تنبيه. */
class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.schedule.index', ['items' => $this->items($request)]);
    }

    public function cmsIndex(Request $request): View
    {
        return view('cms.schedule.index', ['items' => $this->items($request)]);
    }

    private function items(Request $request)
    {
        return StudentScheduleItem::where('user_id', $request->user()->id)
            ->orderBy('day')->orderBy('starts_at')->get()->map->toItem()->values();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        if (StudentScheduleItem::where('user_id', $user->id)->count() >= StudentScheduleItem::MAX_PER_USER) {
            throw ValidationException::withMessages(['title' => __('student.schedule.too_many')]);
        }

        $item = StudentScheduleItem::create($data + ['user_id' => $user->id]);

        return response()->json($item->toItem(), 201);
    }

    public function update(Request $request, int $item): JsonResponse
    {
        $row = StudentScheduleItem::where('user_id', $request->user()->id)->findOrFail($item);
        $row->update($this->validated($request));

        return response()->json($row->fresh()->toItem());
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        StudentScheduleItem::where('user_id', $request->user()->id)->findOrFail($item)->delete();

        return response()->json(['ok' => true]);
    }

    public function clear(Request $request): JsonResponse
    {
        StudentScheduleItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $time = ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:'.implode(',', StudentScheduleItem::KINDS)],
            'day' => ['required', 'integer', 'between:0,6'],
            'start' => $time,
            'end' => $time,
            'room' => ['nullable', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($data['end'] <= $data['start']) {
            throw ValidationException::withMessages(['end' => __('student.schedule.end_after_start')]);
        }

        $title = trim(strip_tags($data['title']));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => __('validation.required', ['attribute' => 'title'])]);
        }

        return [
            'title' => $title,
            'kind' => $data['kind'],
            'day' => (int) $data['day'],
            'starts_at' => $data['start'],
            'ends_at' => $data['end'],
            'room' => isset($data['room']) ? trim(strip_tags($data['room'])) ?: null : null,
            'color' => strtolower($data['color']),
        ];
    }
}
