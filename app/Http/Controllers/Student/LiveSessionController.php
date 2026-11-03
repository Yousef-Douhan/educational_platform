<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LiveSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * جلسات دورات الطالب. رابط الاجتماع لا يُطبع في الصفحة أبدًا:
 * يمر عبر join الذي يتحقق من الوصول والوقت ثم يحوّل إلى رابط https فقط.
 */
class LiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        $courseIds = $this->courseIds($request);
        $now = now();

        $base = LiveSession::query()->whereIn('course_id', $courseIds)->with('course');

        $upcoming = (clone $base)->where('status', 'scheduled')->where('ends_at', '>', $now)
            ->orderBy('scheduled_at')->limit(50)->get();

        $past = (clone $base)->where(fn ($q) => $q->where('status', '!=', 'scheduled')->orWhere('ends_at', '<=', $now))
            ->orderByDesc('scheduled_at')->limit(15)->get();

        return view('student.live.index', ['upcoming' => $upcoming, 'past' => $past, 'now' => $now]);
    }

    public function join(Request $request, int $session): RedirectResponse
    {
        $live = LiveSession::query()->whereIn('course_id', $this->courseIds($request))->findOrFail($session);

        $opensAt = $live->scheduled_at->copy()->subMinutes(LiveSession::JOIN_EARLY_MINUTES);

        if ($live->status !== 'scheduled' || now()->lt($opensAt) || now()->gte($live->ends_at)) {
            return redirect()->route('student.live.index')->withErrors(['live' => __('student.live.not_open')]);
        }

        $url = (string) $live->meeting_url;

        if (! preg_match('#^https://#i', $url) || str_contains(explode('/', substr($url, 8), 2)[0], '@')) {
            return redirect()->route('student.live.index')->withErrors(['live' => __('student.live.bad_link')]);
        }

        return redirect()->away($url);
    }

    private function courseIds(Request $request)
    {
        return Enrollment::query()->grantingAccess()->where('user_id', $request->user()->id)->select('course_id');
    }
}
