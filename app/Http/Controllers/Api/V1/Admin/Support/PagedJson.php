<?php

namespace App\Http\Controllers\Api\V1\Admin\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** أدوات مشتركة لقوائم الإدارة: ترقيم صفحات JSON موحّد وتنظيف معاملات الاستعلام. */
trait PagedJson
{
    protected function page(LengthAwarePaginator $p, callable $map, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => $p->getCollection()->map($map)->values()->all(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
            ],
        ] + $extra);
    }

    protected function str(Request $request, string $key): ?string
    {
        $v = $request->query($key);

        return is_string($v) && $v !== '' ? $v : null;
    }

    protected function int(Request $request, string $key): ?int
    {
        $v = $request->query($key);

        return is_scalar($v) && ctype_digit((string) $v) ? (int) $v : null;
    }

    protected function date(Request $request, string $key): ?string
    {
        $v = $this->str($request, $key);

        return $v !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    protected function per(Request $request, int $default = 15): int
    {
        return min(max((int) $request->query('per_page', $default), 5), 100);
    }

    /** @return array{0:string,1:string} */
    protected function sort(Request $request, array $allowed, string $default): array
    {
        $s = $request->query('sort');

        return [in_array($s, $allowed, true) ? $s : $default, $request->query('dir') === 'asc' ? 'asc' : 'desc'];
    }

    protected function who($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'deleted' => $user->trashed()] : null;
    }

    protected function courseRef($course): ?array
    {
        return $course ? ['id' => $course->id, 'title_ar' => $course->title_ar, 'title_en' => $course->title_en, 'deleted' => $course->trashed()] : null;
    }

    protected function iso($d): ?string
    {
        return $d?->toIso8601String();
    }
}
