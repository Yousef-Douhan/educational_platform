@extends('cms.parent')

@section('title', __('dashboard.title'))

@push('styles')
<style>
    .db { --d-surface:#fff; --d-wash:#f8fafc; --d-line:#e2e8f0; --d-ink:#0f172a; --d-ink2:#475569; --d-ink3:#94a3b8;
          --d-brand:#0284c7; --d-brand-soft:#e0f2fe; --d-ok:#059669; --d-ok-soft:#d1fae5; --d-warn:#d97706; --d-warn-soft:#fef3c7;
          --d-bad:#dc2626; --d-bad-soft:#fee2e2; --d-vio:#7c3aed; --d-vio-soft:#ede9fe; --d-mute:#64748b; --d-mute-soft:#f1f5f9;
          display:flex; flex-direction:column; gap:1.25rem; color:var(--d-ink); }
    html.dark .db { --d-surface:#111a2e; --d-wash:#0f1830; --d-line:#28365a; --d-ink:#e8edf8; --d-ink2:#aab6cf; --d-ink3:#7c8aa8;
          --d-brand:#38bdf8; --d-brand-soft:#0c2a40; --d-ok:#4ade80; --d-ok-soft:#0f2a1d; --d-warn:#fbbf24; --d-warn-soft:#2b2110;
          --d-bad:#f87171; --d-bad-soft:#301616; --d-vio:#a78bfa; --d-vio-soft:#251d46; --d-mute:#94a3b8; --d-mute-soft:#1a2540; }
    .db * { box-sizing: border-box; }
    :where(.db a) { text-decoration: none; color: inherit; }

    .db-hero { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:1rem; }
    .db-hello { margin:0; font-size:1.6rem; font-weight:800; letter-spacing:-.02em; line-height:1.2; color:var(--d-ink); }
    .db-sub { margin:.35rem 0 0; color:var(--d-ink2); font-size:.92rem; }
    .db-quick { display:flex; flex-wrap:wrap; gap:.5rem; }
    .db-qbtn { display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border:1px solid var(--d-line); border-radius:.75rem;
               background:var(--d-surface); color:var(--d-ink); font-size:.85rem; font-weight:600; transition:.15s; }
    .db-qbtn:hover { border-color:var(--d-brand); color:var(--d-brand); transform:translateY(-1px); }
    .db-qbtn--primary { background:var(--d-brand); border-color:var(--d-brand); color:#fff; }
    html.dark .db-qbtn--primary { color:#0b1120; }
    .db-qbtn--primary:hover { color:#fff; opacity:.92; }
    html.dark .db-qbtn--primary:hover { color:#0b1120; }

    .db-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
    @media (max-width: 1099px) { .db-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 479px) { .db-kpis { grid-template-columns:1fr; } }
    .db-card { background:var(--d-surface); border:1px solid var(--d-line); border-radius:1rem; padding:1.1rem 1.2rem; min-width:0; }
    .db-kpi { display:flex; gap:.9rem; align-items:flex-start; position:relative; overflow:hidden; }
    .db-ico { flex:none; width:2.75rem; height:2.75rem; border-radius:.85rem; display:flex; align-items:center; justify-content:center; font-size:1.05rem; }
    .db-ico--brand { background:var(--d-brand-soft); color:var(--d-brand); } .db-ico--ok { background:var(--d-ok-soft); color:var(--d-ok); }
    .db-ico--warn { background:var(--d-warn-soft); color:var(--d-warn); } .db-ico--vio { background:var(--d-vio-soft); color:var(--d-vio); }
    .db-ico--bad { background:var(--d-bad-soft); color:var(--d-bad); }
    .db-kpi-label { font-size:.8rem; color:var(--d-ink2); font-weight:600; margin:0; }
    .db-kpi-value { margin:.25rem 0 0; font-size:clamp(1.3rem,1.5vw,1.65rem); font-weight:800; letter-spacing:-.02em; line-height:1.15; color:var(--d-ink); overflow-wrap:anywhere; }
    .db-kpi-value small { display:block; font-size:.72rem; font-weight:700; color:var(--d-ink3); margin-top:.1rem; }
    .db-kpi > div { min-width:0; flex:1; }
    .db-kpi-foot { margin:.4rem 0 0; font-size:.76rem; color:var(--d-ink3); display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
    .db-chip { display:inline-flex; align-items:center; gap:.25rem; padding:.1rem .45rem; border-radius:999px; font-size:.72rem; font-weight:700; }
    .db-chip--up { background:var(--d-ok-soft); color:var(--d-ok); } .db-chip--down { background:var(--d-bad-soft); color:var(--d-bad); }
    .db-kpi--link { transition:.15s; } .db-kpi--link:hover { border-color:var(--d-brand); transform:translateY(-2px); }

    .db-grid { display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width: 1100px) { .db-grid--2-1 { grid-template-columns:minmax(0,2fr) minmax(0,1fr); } .db-grid--1-1 { grid-template-columns:repeat(2,minmax(0,1fr)); } }

    .db-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
    .db-title { margin:0; font-size:1rem; font-weight:700; color:var(--d-ink); }
    .db-hint { margin:.15rem 0 0; font-size:.78rem; color:var(--d-ink3); }
    .db-link { font-size:.8rem; font-weight:600; color:var(--d-brand); white-space:nowrap; }
    .db-link:hover { text-decoration:underline !important; }

    .db-bars { display:flex; align-items:flex-end; gap:.5rem; height:200px; padding-top:.5rem; }
    .db-bar-col { flex:1; min-width:0; height:100%; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; gap:.4rem; }
    .db-bar { width:100%; max-width:34px; border-radius:.5rem .5rem .2rem .2rem; background:linear-gradient(180deg,var(--d-brand),color-mix(in srgb,var(--d-brand) 45%,transparent)); transition:.2s; min-height:3px; }
    .db-bar-col:hover .db-bar { filter:brightness(1.12); }
    .db-bar--empty { background:var(--d-line); opacity:.7; }
    .db-bar-lbl { font-size:.68rem; color:var(--d-ink3); white-space:nowrap; }
    .db-line { width:100%; height:200px; display:block; }
    .db-line-lbls { display:flex; justify-content:space-between; margin-top:.4rem; font-size:.68rem; color:var(--d-ink3); }
    .db-line-lbls span { flex:1; text-align:center; }

    .db-rows { display:flex; flex-direction:column; gap:.85rem; }
    .db-row-top { display:flex; justify-content:space-between; font-size:.82rem; margin-bottom:.35rem; gap:.5rem; }
    .db-row-top span:first-child { color:var(--d-ink2); font-weight:600; } .db-row-top span:last-child { color:var(--d-ink); font-weight:700; }
    .db-track { height:.5rem; border-radius:999px; background:var(--d-mute-soft); overflow:hidden; }
    .db-fill { height:100%; border-radius:999px; }
    .db-fill--ok { background:var(--d-ok); } .db-fill--warn { background:var(--d-warn); } .db-fill--bad { background:var(--d-bad); }
    .db-fill--mute { background:var(--d-mute); } .db-fill--vio { background:var(--d-vio); } .db-fill--brand { background:var(--d-brand); }

    .db-rank { display:flex; flex-direction:column; gap:.7rem; }
    .db-rank-item { display:flex; align-items:center; gap:.75rem; min-width:0; }
    .db-rank-n { flex:none; width:1.7rem; height:1.7rem; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; background:var(--d-brand-soft); color:var(--d-brand); }
    .db-rank-t { flex:1; min-width:0; }
    .db-rank-t b { display:block; font-size:.86rem; font-weight:600; color:var(--d-ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .db-rank-t .db-track { margin-top:.35rem; height:.35rem; }
    .db-rank-c { flex:none; font-size:.76rem; color:var(--d-ink3); white-space:nowrap; }

    .db-table-wrap { overflow-x:auto; }
    .db-table { width:100%; border-collapse:collapse; font-size:.85rem; min-width:480px; }
    .db-table th { text-align:start; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--d-ink3); font-weight:700; padding:.5rem .6rem; border-bottom:1px solid var(--d-line); white-space:nowrap; }
    .db-table td { padding:.7rem .6rem; border-bottom:1px solid var(--d-line); color:var(--d-ink2); white-space:nowrap; }
    .db-table tr:last-child td { border-bottom:0; }
    .db-table tbody tr:hover td { background:var(--d-wash); }
    .db-table td b { color:var(--d-ink); font-weight:700; }
    .db-st { display:inline-block; padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; }
    .db-st--pending,.db-st--scheduled { background:var(--d-warn-soft); color:var(--d-warn); } .db-st--paid,.db-st--approved,.db-st--active { background:var(--d-ok-soft); color:var(--d-ok); }
    .db-st--failed,.db-st--rejected { background:var(--d-bad-soft); color:var(--d-bad); } .db-st--cancelled,.db-st--expired { background:var(--d-mute-soft); color:var(--d-mute); }
    .db-st--refunded { background:var(--d-vio-soft); color:var(--d-vio); } .db-st--completed { background:var(--d-brand-soft); color:var(--d-brand); }

    .db-list { display:flex; flex-direction:column; }
    .db-li { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--d-line); min-width:0; }
    .db-li:last-child { border-bottom:0; padding-bottom:0; } .db-li:first-child { padding-top:0; }
    .db-li-main { flex:1; min-width:0; } .db-li-main b { display:block; font-size:.86rem; color:var(--d-ink); font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .db-li-main span { font-size:.75rem; color:var(--d-ink3); }
    .db-empty { text-align:center; padding:1.6rem .5rem; color:var(--d-ink3); font-size:.85rem; }
    .db-empty i { display:block; font-size:1.4rem; margin-bottom:.5rem; opacity:.7; }
    @media (max-width: 640px) { .db-hello { font-size:1.3rem; } .db-bars { gap:.25rem; } .db-bar-lbl { font-size:.6rem; } .db-card { padding:.9rem; } }
</style>
@endpush

@section('main-content')
@php
    $ar = app()->isLocale('ar');
    $fmt = fn ($n) => number_format((float) $n, 2);
    $kpi = $kpi ?? [];
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')->toString();
    $deltaChip = function ($d) {
        if ($d === null) return '<span>'.e(__('dashboard.kpi.no_change')).'</span>';
        $up = $d >= 0;
        return '<span class="db-chip db-chip--'.($up ? 'up' : 'down').'"><i class="fa-solid fa-arrow-'.($up ? 'trend-up' : 'trend-down').'"></i> '.'<bdi dir="ltr">'.($up ? '+' : '').$d.'%</bdi></span><span>'.e(__('dashboard.kpi.vs_last')).'</span>';
    };
    $statusTone = ['pending' => 'warn', 'paid' => 'ok', 'failed' => 'bad', 'cancelled' => 'mute', 'refunded' => 'vio',
                   'active' => 'ok', 'completed' => 'brand', 'expired' => 'warn'];
@endphp

<div class="db">
    {{-- ============ Hero ============ --}}
    <div class="db-hero">
        <div>
            <h1 class="db-hello">{{ __('dashboard.hello', ['name' => $firstName]) }}</h1>
            <p class="db-sub">{{ __('dashboard.subtitle') }} · {{ now()->locale($ar ? 'ar' : 'en')->translatedFormat('l، j F Y') }}</p>
        </div>
        <div class="db-quick">
            @can('courses.create')<a class="db-qbtn db-qbtn--primary" href="{{ route('cms.admin.courses.index') }}"><i class="fa-solid fa-plus"></i> {{ __('dashboard.quick.add_course') }}</a>@endcan
            @can('users.view')<a class="db-qbtn" href="{{ route('cms.admin.users.index') }}"><i class="fa-solid fa-user-plus"></i> {{ __('dashboard.quick.add_user') }}</a>@endcan
            @can('orders.view')<a class="db-qbtn" href="{{ route('cms.admin.orders.index') }}"><i class="fa-solid fa-receipt"></i> {{ __('dashboard.quick.orders') }}</a>@endcan
            @can('live-sessions.view')<a class="db-qbtn" href="{{ route('cms.admin.live-sessions.index') }}"><i class="fa-solid fa-video"></i> {{ __('dashboard.quick.live') }}</a>@endcan
        </div>
    </div>

    {{-- ============ KPIs ============ --}}
    <section class="db-kpis">
        @isset($kpi['revenue'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.orders.index') }}">
                <span class="db-ico db-ico--ok"><i class="fa-solid fa-sack-dollar"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.revenue') }}</p>
                    <p class="db-kpi-value" dir="ltr">{{ $fmt($kpi['revenue']['value']) }}<small>{{ $kpi['revenue']['currency'] }}</small></p>
                    <p class="db-kpi-foot">{!! $deltaChip($kpi['revenue']['delta']) !!}</p>
                    @if (! empty($kpi['revenue']['others']))<p class="db-kpi-foot" dir="ltr">+ {{ implode(' · ', $kpi['revenue']['others']) }}</p>@endif
                </div>
            </a>
        @endisset
        @isset($kpi['users'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.users.index') }}">
                <span class="db-ico db-ico--brand"><i class="fa-solid fa-users"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.users') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['users']['value']) }}</p>
                    <p class="db-kpi-foot">{!! $deltaChip($kpi['users']['delta']) !!}</p>
                </div>
            </a>
        @endisset
        @isset($kpi['enrollments'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.enrollments.index') }}">
                <span class="db-ico db-ico--vio"><i class="fa-solid fa-user-graduate"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.enrollments') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['enrollments']['value']) }}</p>
                    <p class="db-kpi-foot">{!! $deltaChip($kpi['enrollments']['delta']) !!}</p>
                </div>
            </a>
        @endisset
        @isset($kpi['courses'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.courses.index') }}">
                <span class="db-ico db-ico--brand"><i class="fa-solid fa-graduation-cap"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.courses') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['courses']['value']) }}</p>
                    <p class="db-kpi-foot"><span>{{ __('dashboard.kpi.of_total', ['n' => $kpi['courses']['sub']]) }}</span></p>
                </div>
            </a>
        @endisset
        @isset($kpi['pendingOrders'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.orders.index', ['status' => 'pending']) }}">
                <span class="db-ico db-ico--warn"><i class="fa-solid fa-hourglass-half"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.pending_orders') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['pendingOrders']['value']) }}</p>
                </div>
            </a>
        @endisset
        @isset($kpi['pendingReceipts'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.receipts.index') }}">
                <span class="db-ico db-ico--bad"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.pending_receipts') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['pendingReceipts']['value']) }}</p>
                </div>
            </a>
        @endisset
        @isset($kpi['live'])
            <a class="db-card db-kpi db-kpi--link" href="{{ route('cms.admin.live-sessions.index') }}">
                <span class="db-ico db-ico--vio"><i class="fa-solid fa-video"></i></span>
                <div>
                    <p class="db-kpi-label">{{ __('dashboard.kpi.live') }}</p>
                    <p class="db-kpi-value">{{ number_format($kpi['live']['value']) }}</p>
                </div>
            </a>
        @endisset
    </section>

    {{-- ============ Revenue + order status ============ --}}
    @isset($revenueSeries)
        @php $maxRev = max(1, max($revenueSeries)); @endphp
        <div class="db-grid db-grid--2-1">
            <section class="db-card">
                <div class="db-head">
                    <div>
                        <h2 class="db-title">{{ __('dashboard.charts.revenue') }} <span dir="ltr" style="color:var(--d-ink3);font-weight:600">({{ $currency }})</span></h2>
                        <p class="db-hint">{{ __('dashboard.charts.revenue_sub') }}</p>
                    </div>
                </div>
                <div class="db-bars" dir="ltr">
                    @foreach ($months as $i => $m)
                        @php $v = $revenueSeries[$i]; @endphp
                        <div class="db-bar-col" title="{{ $m['full'] }}: {{ $fmt($v) }} {{ $currency }}">
                            <div class="db-bar {{ $v <= 0 ? 'db-bar--empty' : '' }}" style="height: {{ $v > 0 ? max(4, round($v / $maxRev * 100)) : 2 }}%"></div>
                            <span class="db-bar-lbl">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="db-card">
                <div class="db-head"><h2 class="db-title">{{ __('dashboard.charts.orders_by_status') }}</h2></div>
                @php $orderTotal = max(1, array_sum($orderStatus)); @endphp
                <div class="db-rows">
                    @foreach ($orderStatus as $st => $n)
                        <div>
                            <div class="db-row-top"><span>{{ __('orders.statuses.'.$st) }}</span><span>{{ number_format($n) }}</span></div>
                            <div class="db-track"><div class="db-fill db-fill--{{ $statusTone[$st] ?? 'mute' }}" style="width: {{ round($n / $orderTotal * 100) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endisset

    {{-- ============ Enrollments + top courses ============ --}}
    @isset($enrollSeries)
        @php
            $maxEn = max(1, max($enrollSeries));
            $W = 600; $H = 180; $pad = 12; $n = count($enrollSeries);
            $pts = [];
            foreach ($enrollSeries as $i => $v) {
                $x = $n > 1 ? $pad + ($W - 2 * $pad) * $i / ($n - 1) : $W / 2;
                $y = $H - $pad - ($H - 2 * $pad) * ($v / $maxEn);
                $pts[] = round($x, 1).','.round($y, 1);
            }
            $line = implode(' ', $pts);
            $area = $pad.','.($H - $pad).' '.$line.' '.($W - $pad).','.($H - $pad);
        @endphp
        <div class="db-grid db-grid--2-1">
            <section class="db-card">
                <div class="db-head">
                    <div>
                        <h2 class="db-title">{{ __('dashboard.charts.enrollments') }}</h2>
                        <p class="db-hint">{{ __('dashboard.charts.enrollments_sub') }}</p>
                    </div>
                </div>
                <div dir="ltr">
                    <svg class="db-line" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" role="img" aria-label="{{ __('dashboard.charts.enrollments') }}">
                        <defs><linearGradient id="dbArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#8b5cf6" stop-opacity=".35"/><stop offset="1" stop-color="#8b5cf6" stop-opacity="0"/></linearGradient></defs>
                        @foreach ([0.25, 0.5, 0.75] as $g)
                            <line x1="0" x2="{{ $W }}" y1="{{ $H * $g }}" y2="{{ $H * $g }}" stroke="currentColor" stroke-opacity=".12" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" style="color:var(--d-ink3)"/>
                        @endforeach
                        <polygon points="{{ $area }}" fill="url(#dbArea)"/>
                        <polyline points="{{ $line }}" fill="none" stroke="#8b5cf6" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>
                    <div class="db-line-lbls">
                        @foreach ($months as $i => $m)<span title="{{ $m['full'] }}: {{ $enrollSeries[$i] }}">{{ $m['label'] }}</span>@endforeach
                    </div>
                </div>
            </section>

            <section class="db-card">
                <div class="db-head">
                    <div>
                        <h2 class="db-title">{{ __('dashboard.charts.top_courses') }}</h2>
                        <p class="db-hint">{{ __('dashboard.charts.top_courses_sub') }}</p>
                    </div>
                </div>
                @if (count($topCourses))
                    @php $topMax = max(1, $topCourses[0]['count']); @endphp
                    <div class="db-rank">
                        @foreach ($topCourses as $i => $c)
                            <div class="db-rank-item">
                                <span class="db-rank-n">{{ $i + 1 }}</span>
                                <div class="db-rank-t">
                                    <b title="{{ $c['title'] }}">{{ $c['title'] }}</b>
                                    <div class="db-track"><div class="db-fill db-fill--brand" style="width: {{ round($c['count'] / $topMax * 100) }}%"></div></div>
                                </div>
                                <span class="db-rank-c">{{ __('dashboard.charts.students', ['n' => $c['count']]) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="db-empty"><i class="fa-regular fa-chart-bar"></i>{{ __('dashboard.charts.no_data') }}</div>
                @endif
            </section>
        </div>
    @endisset

    {{-- ============ Recent orders + side lists ============ --}}
    @if (isset($recentOrders) || isset($pendingReceipts) || isset($upcomingSessions))
        <div class="db-grid db-grid--2-1">
            @isset($recentOrders)
                <section class="db-card">
                    <div class="db-head">
                        <h2 class="db-title">{{ __('dashboard.lists.recent_orders') }}</h2>
                        <a class="db-link" href="{{ route('cms.admin.orders.index') }}">{{ __('dashboard.lists.view_all') }}</a>
                    </div>
                    @if ($recentOrders->count())
                        <div class="db-table-wrap">
                            <table class="db-table">
                                <thead><tr>
                                    <th>{{ __('dashboard.lists.order') }}</th><th>{{ __('dashboard.lists.student') }}</th>
                                    <th>{{ __('dashboard.lists.total') }}</th><th>{{ __('dashboard.lists.status') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($recentOrders as $o)
                                        <tr>
                                            <td><a href="{{ route('cms.admin.orders.index', ['open' => $o->id]) }}"><b dir="ltr">#{{ $o->order_number }}</b></a></td>
                                            <td>{{ $o->user->name ?? __('dashboard.lists.deleted_user') }}</td>
                                            <td dir="ltr">{{ $fmt($o->total_amount) }} {{ $o->currency }}</td>
                                            <td><span class="db-st db-st--{{ $o->status }}">{{ __('orders.statuses.'.$o->status) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="db-empty"><i class="fa-solid fa-receipt"></i>{{ __('dashboard.lists.empty_orders') }}</div>
                    @endif
                </section>
            @endisset

            <div style="display:flex;flex-direction:column;gap:1rem;min-width:0">
                @isset($pendingReceipts)
                    <section class="db-card">
                        <div class="db-head">
                            <h2 class="db-title">{{ __('dashboard.lists.pending_receipts') }}</h2>
                            <a class="db-link" href="{{ route('cms.admin.receipts.index') }}">{{ __('dashboard.lists.view_all') }}</a>
                        </div>
                        @if ($pendingReceipts->count())
                            <div class="db-list">
                                @foreach ($pendingReceipts as $r)
                                    <a class="db-li" href="{{ route('cms.admin.receipts.show', $r->id) }}">
                                        <span class="db-ico db-ico--warn" style="width:2.2rem;height:2.2rem;font-size:.85rem"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                        <div class="db-li-main">
                                            <b>{{ $r->user->name ?? __('dashboard.lists.deleted_user') }}</b>
                                            <span dir="ltr">{{ __('receipts.methods.'.$r->method) }} · {{ $fmt($r->order->total_amount ?? 0) }} {{ $r->order->currency ?? '' }}</span>
                                        </div>
                                        <span class="db-link">{{ __('dashboard.lists.review') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="db-empty"><i class="fa-regular fa-circle-check"></i>{{ __('dashboard.lists.empty_receipts') }}</div>
                        @endif
                    </section>
                @endisset

                @isset($upcomingSessions)
                    <section class="db-card">
                        <div class="db-head">
                            <h2 class="db-title">{{ __('dashboard.lists.upcoming') }}</h2>
                            <a class="db-link" href="{{ route('cms.admin.live-sessions.index') }}">{{ __('dashboard.lists.view_all') }}</a>
                        </div>
                        @if ($upcomingSessions->count())
                            <div class="db-list">
                                @foreach ($upcomingSessions as $s)
                                    <a class="db-li" href="{{ route('cms.admin.live-sessions.index', ['open' => $s->id]) }}">
                                        <span class="db-ico db-ico--vio" style="width:2.2rem;height:2.2rem;font-size:.85rem"><i class="fa-solid fa-video"></i></span>
                                        <div class="db-li-main">
                                            <b>{{ $ar ? $s->title_ar : $s->title_en }}</b>
                                            <span dir="ltr">{{ $s->scheduled_at->locale($ar ? 'ar' : 'en')->translatedFormat('D j M · H:i') }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="db-empty"><i class="fa-regular fa-calendar"></i>{{ __('dashboard.lists.empty_live') }}</div>
                        @endif
                    </section>
                @endisset
            </div>
        </div>
    @endif
</div>
@endsection
