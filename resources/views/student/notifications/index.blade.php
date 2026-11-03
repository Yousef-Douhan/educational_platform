@extends('student.layout')

@section('title', __('student.nav.notifications'))

@php
    $tones = [
        'success' => 'bg-emerald-100 text-emerald-700', 'warning' => 'bg-amber-100 text-amber-700',
        'primary' => 'bg-indigo-100 text-indigo-700', 'danger' => 'bg-rose-50 text-rose-600',
        'info' => 'bg-sky-50 text-sky-700', 'muted' => 'bg-slate-100 text-slate-500',
    ];
    $link = function (array $view, $row) {
        $p = $row->data['params'] ?? [];
        $num = is_string($p['order_number'] ?? null) && preg_match('/^ORD-[A-Z0-9]{8}$/', $p['order_number']) ? $p['order_number'] : null;

        return match (true) {
            str_starts_with($view['kind'], 'order.') || $view['kind'] === 'receipt.rejected' => $num ? route('student.orders.show', $num) : route('student.orders.index'),
            $view['kind'] === 'enrollment.activated' => route('student.courses.mine'),
            str_starts_with($view['kind'], 'certificate.') => route('student.certificates.index'),
            str_starts_with($view['kind'], 'live_session.') => route('student.live.index'),
            default => null,
        };
    };
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900"><i class="fa-solid fa-bell me-2 text-indigo-600"></i>{{ __('student.nav.notifications') }}</h1>
            <p class="text-sm text-slate-500">{{ __('student.notif.subtitle', ['n' => $unread]) }}</p>
        </div>
        <div class="flex items-center gap-2 text-sm font-semibold">
            <a href="{{ route('student.notifications.index', $onlyUnread ? [] : ['state' => 'unread']) }}" class="rounded-full border border-slate-200 px-4 py-2 hover:bg-slate-50">
                {{ $onlyUnread ? __('student.notif.show_all') : __('notifications.mine.only_unread') }}
            </a>
            @if ($unread > 0)
                <form method="POST" action="{{ route('student.notifications.read-all') }}">@csrf
                    <button class="rounded-full bg-indigo-700 px-4 py-2 text-white hover:bg-indigo-800">{{ __('notifications.mine.mark_all') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if ($records->count())
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @foreach ($records as $row)
                @php $view = \App\Services\NotificationPresenter::present($row); $url = $link($view, $row); @endphp
                <li class="flex items-start gap-4 p-4 {{ $row->read_at ? '' : 'bg-indigo-50' }}">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $tones[$view['tone']] ?? $tones['muted'] }}"><i class="fa-solid {{ $view['icon'] }}"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-900">{{ $view['title'] }}
                            @unless ($row->read_at)<span class="ms-1 rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ __('notifications.mine.new') }}</span>@endunless
                        </p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $view['body'] }}</p>
                        <p class="mt-1 text-xs text-slate-400" dir="ltr">{{ $row->created_at->format('Y-m-d H:i') }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs font-semibold">
                            @if ($url) <a href="{{ $url }}" class="text-indigo-700 hover:underline">{{ __('student.notif.open') }}</a> @endif
                            @unless ($row->read_at)
                                <form method="POST" action="{{ route('student.notifications.read', $row->id) }}">@csrf<button class="text-slate-500 hover:text-indigo-700">{{ __('notifications.mine.mark_read') }}</button></form>
                            @endunless
                            <form method="POST" action="{{ route('student.notifications.destroy', $row->id) }}">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">{{ __('notifications.mine.delete') }}</button></form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-5">{{ $records->links() }}</div>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <i class="fa-regular fa-bell-slash text-4xl text-slate-400"></i>
            <p class="mt-3 font-bold text-slate-800">{{ __('notifications.mine.empty_title') }}</p>
            <p class="text-sm text-slate-500">{{ __('notifications.mine.empty_text') }}</p>
        </div>
    @endif
@endsection
