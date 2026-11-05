<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جلسات دورات الطالب. رابط الاجتماع لا يظهر في القائمة أبدًا:
 * يخرج فقط من join بعد التحقق من الوصول والوقت، ويكون https بلا بيانات اعتماد.
 */
class LiveSessionController extends Controller
{
    use RespondsWithErrors;

    public function index(Request $request): JsonResponse
    {
        $now  = now();
        $base = LiveSession::query()->whereIn('course_id', $this->courseIds($request))->with('course');

        $upcoming = (clone $base)->where('status', 'scheduled')->where('ends_at', '>', $now)
            ->orderBy('scheduled_at')->limit(50)->get();
        $past = (clone $base)->where(fn ($q) => $q->where('status', '!=', 'scheduled')->orWhere('ends_at', '<=', $now))
            ->orderByDesc('scheduled_at')->limit(15)->get();

        return response()->json(['data' => [
            'upcoming' => $upcoming->map(fn ($s) => $this->row($s, $now))->values(),
            'past'     => $past->map(fn ($s) => $this->row($s, $now))->values(),
        ]]);
    }

    public function join(Request $request, int $session): JsonResponse
    {
        $live    = LiveSession::query()->whereIn('course_id', $this->courseIds($request))->findOrFail($session);
        $opensAt = $live->scheduled_at->copy()->subMinutes(LiveSession::JOIN_EARLY_MINUTES);

        if ($live->status !== 'scheduled' || now()->lt($opensAt) || now()->gte($live->ends_at)) {
            return $this->failMessage(__('student.live.not_open'), 'not_open');
        }

        $url = (string) $live->meeting_url;
        if (! preg_match('#^https://#i', $url) || str_contains(explode('/', substr($url, 8), 2)[0], '@')) {
            return $this->failMessage(__('student.live.bad_link'), 'bad_link');
        }

        return response()->json(['data' => ['meeting_url' => $url]]);
    }

    private function row(LiveSession $s, $now): array
    {
        return [
            'id' => $s->id,
            'title' => Loc::pick($s->title_ar, $s->title_en),
            'course' => $s->course ? ['id' => $s->course->id, 'slug' => $s->course->slug, 'title' => Loc::pick($s->course->title_ar, $s->course->title_en)] : null,
            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
            'ends_at' => $s->ends_at?->toIso8601String(),
            'duration_minutes' => $s->duration_minutes,
            'phase' => $s->phase($now),
            'can_join' => $s->status === 'scheduled'
                && $now->gte($s->scheduled_at->copy()->subMinutes(LiveSession::JOIN_EARLY_MINUTES))
                && $now->lt($s->ends_at),
        ];
    }

    private function courseIds(Request $request)
    {
        return Enrollment::query()->grantingAccess()->where('user_id', $request->user()->id)->select('course_id');
    }
}
