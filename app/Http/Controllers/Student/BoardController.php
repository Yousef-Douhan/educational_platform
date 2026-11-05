<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentBoardPage;
use App\Support\BoardSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** صفحات سبورة الطالب (JSON لعناصر الرسم). تُنقّى في الخادم، وكل شيء مقيَّد بصاحبه. */
class BoardController extends Controller
{
    public function show(Request $request, StudentBoardPage $page): JsonResponse
    {
        $this->own($request, $page);

        return response()->json(['id' => $page->id, 'name' => $page->name,
            'objects' => $page->objects ? (json_decode($page->objects, true) ?: []) : []]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);

        $count = StudentBoardPage::where('user_id', $user->id)->count();
        if ($count >= StudentBoardPage::MAX_PER_USER) {
            return response()->json(['message' => __('student.notes.board.limit')], 422);
        }

        $page = StudentBoardPage::create([
            'user_id'  => $user->id,
            'name'     => trim($data['name'] ?? '') !== '' ? trim($data['name']) : __('student.notes.board.page', ['n' => $count + 1]),
            'position' => (int) StudentBoardPage::where('user_id', $user->id)->max('position') + 1,
            'objects'  => '[]',
        ]);

        return response()->json(['id' => $page->id, 'name' => $page->name], 201);
    }

    public function update(Request $request, StudentBoardPage $page): JsonResponse
    {
        $this->own($request, $page);

        $data = $request->validate([
            'name'      => ['nullable', 'string', 'max:60'],
            'objects'   => ['nullable', 'array', 'max:' . BoardSanitizer::MAX_OBJECTS],
        ]);

        $attrs = ['objects' => json_encode(BoardSanitizer::clean($data['objects'] ?? []))];
        if (isset($data['name']) && trim($data['name']) !== '') {
            $attrs['name'] = trim($data['name']);
        }
        $page->update($attrs);

        return response()->json(['id' => $page->id, 'name' => $page->name]);
    }

    public function destroy(Request $request, StudentBoardPage $page): JsonResponse
    {
        $this->own($request, $page);
        $page->delete();

        return response()->json(['ok' => true]);
    }

    private function own(Request $request, StudentBoardPage $page): void
    {
        abort_unless($page->user_id === $request->user()->id, 404);
    }
}
