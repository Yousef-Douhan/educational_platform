@extends('student.layout')

@section('title', __('student.nav.live_sessions'))

@php
    $badge = [
        'upcoming' => 'bg-sky-50 text-sky-800', 'live' => 'bg-emerald-100 text-emerald-800',
        'ended' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-rose-50 text-rose-700',
    ];
    $card = function ($s) use ($now, $badge) {
        $phase = $s->phase($now);
        $opens = $s->scheduled_at->copy()->subMinutes(\App\Models\LiveSession::JOIN_EARLY_MINUTES);
        $canJoin = $s->status === 'scheduled' && $now->gte($opens) && $now->lt($s->ends_at);
        return compact('s', 'phase', 'opens', 'canJoin');
    };
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-slate-900"><i class="fa-solid fa-video me-2 text-indigo-600"></i>{{ __('student.nav.live_sessions') }}</h1>
        <p class="text-sm text-slate-500">{{ __('student.live.subtitle', ['m' => \App\Models\LiveSession::JOIN_EARLY_MINUTES]) }}</p>
    </div>

    @if ($errors->has('live'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first('live') }}</div>
    @endif

    <h2 class="mb-3 text-lg font-bold text-slate-900">{{ __('student.live.upcoming') }}</h2>
    @forelse ($upcoming as $s)
        @php extract($card($s)); @endphp
        <div class="mb-3 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-indigo-700">{{ \App\Support\Loc::pick($s->course->title_ar, $s->course->title_en) }}</p>
                <p class="mt-0.5 text-lg font-bold text-slate-900">{{ \App\Support\Loc::pick($s->title_ar, $s->title_en) }}</p>
                <p class="mt-1 text-sm text-slate-500" dir="ltr">
                    <i class="fa-regular fa-calendar me-1"></i>{{ $s->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    · {{ $s->duration_minutes }} {{ __('student.live.min') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[$phase] }}">{{ __('live_sessions.phases.' . $phase) }}</span>
                @if ($canJoin)
                    <a href="{{ route('student.live.join', $s->id) }}" rel="noopener noreferrer" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                        <i class="fa-solid fa-video me-1"></i>{{ __('student.live.join') }}</a>
                @else
                    <span class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-500">{{ __('student.live.opens_at', ['t' => $opens->timezone(config('app.timezone'))->format('H:i')]) }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            <i class="fa-regular fa-calendar-xmark text-3xl"></i>
            <p class="mt-2">{{ __('student.live.none') }}</p>
        </div>
    @endforelse

    @if ($past->isNotEmpty())
        <h2 class="mb-3 mt-10 text-lg font-bold text-slate-900">{{ __('student.live.past') }}</h2>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                @foreach ($past as $s)
                    @php $phase = $s->phase($now); @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
                        <div>
                            <p class="font-semibold text-slate-800">{{ \App\Support\Loc::pick($s->title_ar, $s->title_en) }}</p>
                            <p class="text-xs text-slate-500">{{ \App\Support\Loc::pick($s->course->title_ar, $s->course->title_en) }} · <span dir="ltr">{{ $s->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span></p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[$phase] }}">{{ __('live_sessions.phases.' . $phase) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
