<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @include('student.partials.theme-script')
    <script>try{if(localStorage.getItem('cms-sidebar')==='collapsed')document.documentElement.classList.add('sidebar-collapsed')}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [data-sidebar-collapse] { display: none; }
        @media (min-width: 1024px) {
            [data-sidebar-collapse] { display: inline-flex; }
            html.sidebar-collapsed #cms-sidebar { display: none !important; }
        }

        /* ---------- Responsive ---------- */
        #cms-sidebar { overscroll-behavior: contain; }

        /* ---------- Sidebar theme: فاتح في الوضع الفاتح، غامق متناسق في الداكن ---------- */
        #cms-sidebar {
            --sb-bg: #f8fafc; --sb-border: #e2e8f0; --sb-text: #334155; --sb-strong: #0f172a;
            --sb-muted: #94a3b8; --sb-hover: #eef2f7; --sb-active-bg: #e0e7ff; --sb-active-text: #3730a3;
            --sb-icon: #4f46e5; --sb-card: #eef2f7; --sb-badge-bg: #e0e7ff; --sb-badge-text: #4338ca;
            background-color: var(--sb-bg); color: var(--sb-text); border-color: var(--sb-border);
        }
        html.dark #cms-sidebar {
            --sb-bg: #0b1220; --sb-border: #1c2740; --sb-text: #cbd5e1; --sb-strong: #f1f5f9;
            --sb-muted: #64748b; --sb-hover: #16213a; --sb-active-bg: #1e2a4a; --sb-active-text: #ffffff;
            --sb-icon: #7dd3fc; --sb-card: #111a2e; --sb-badge-bg: rgb(14 165 233 / .18); --sb-badge-text: #7dd3fc;
        }
        #cms-sidebar .border-slate-800, #cms-sidebar .border-slate-700 { border-color: var(--sb-border); }
        #cms-sidebar h1, #cms-sidebar .text-white { color: var(--sb-strong); }
        #cms-sidebar .text-slate-400, #cms-sidebar .text-slate-500 { color: var(--sb-muted); }
        #cms-sidebar nav a { color: var(--sb-text); }
        #cms-sidebar nav a:hover { background-color: var(--sb-hover); color: var(--sb-strong); }
        #cms-sidebar nav a.bg-slate-800, #cms-sidebar nav a.bg-slate-800:hover {
            background-color: var(--sb-active-bg); color: var(--sb-active-text);
        }
        #cms-sidebar .text-sky-400 { color: var(--sb-icon); }
        #cms-sidebar .bg-sky-500\/20 { background-color: var(--sb-badge-bg); }
        #cms-sidebar .text-sky-300 { color: var(--sb-badge-text); }
        #cms-sidebar .bg-slate-900 { background-color: var(--sb-card); }
        #cms-sidebar form button { color: var(--sb-text); }
        #cms-sidebar form button:hover { background-color: var(--sb-hover); color: var(--sb-strong); }
        #cms-sidebar, #cms-sidebar nav { scrollbar-width: none; -ms-overflow-style: none; }
        #cms-sidebar::-webkit-scrollbar, #cms-sidebar nav::-webkit-scrollbar { display: none; width: 0; height: 0; }
        @media (max-width: 1023.98px) {
            #cms-sidebar { box-shadow: 0 0 40px rgba(2, 6, 23, .45); }
            body.cms-menu-open { overflow: hidden; }
        }
        main { min-width: 0; }
        main img, main video, main canvas, main iframe { max-width: 100%; }
        .table-wrap, .table-card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .crud-table { min-width: 640px; }
        @media (max-width: 767.98px) {
            .page-header { flex-direction: column; align-items: stretch; gap: .75rem; }
            .page-header__actions { flex-wrap: wrap; }
            .page-header__actions > * { flex: 1 1 auto; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
            .uu-head { flex-direction: column; align-items: stretch; }
            .uu-title { font-size: 1.35rem; }
            .uu-drawer-panel { width: 100% !important; max-width: 100% !important; }
            .crud-modal__dialog, .crud-modal__dialog--lg { width: 100%; max-width: 100%; }
            main { padding: .75rem !important; }
            .crud-table th, .crud-table td { white-space: nowrap; }
            .crud-table td:not(.col-actions) { max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
            .crud-table td:not(.col-actions) div { overflow: hidden; text-overflow: ellipsis; }
            .crud-table th, .crud-table td { padding-inline: .75rem; }
        }
        @media (max-width: 479.98px) {
            [data-cms-title-label] { display: none; }
            #cms-notif [data-notif-panel] { position: fixed; inset-inline: .75rem; top: 4.5rem; width: auto; max-width: none; }
        }
    </style>
    @stack('styles')

</head>

<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    @include('student.partials.theme-toggle')
    <div class="flex min-h-screen">
        <aside id="cms-sidebar"
            class="fixed inset-y-0 start-0 z-40 hidden w-72 shrink-0 border-e border-slate-800 bg-slate-950 text-slate-100 lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:self-start">
            <div class="flex shrink-0 items-center gap-3 border-b border-slate-800 px-6 py-5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-500/20 text-lg font-bold text-sky-300">
                    E
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Platform</p>
                    <h1 class="text-lg font-semibold">Educational CMS</h1>
                </div>
            </div>

            <nav class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 py-5">
                <a href="{{ route('cms.admin.dashboard') }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.dashboard') ? 'bg-slate-800 text-white' : '' }}">
                    <i class="fa-solid fa-gauge-high w-5 text-sky-400"></i>
                    {{ __('sidebar.dashboard') }}
                </a>

                @canany(['users.view', 'user-profiles.view', 'roles.view', 'permissions.view'])
                    <div class="space-y-1" role="group" aria-labelledby="nav-group-users">
                        <p id="nav-group-users" class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('sidebar.groups.users') }}</p>
                        @can('users.view')
                            <a href="{{ route('cms.admin.users.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.users.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-users w-5 text-sky-400"></i>
                                {{ __('users.title') }}
                            </a>
                        @endcan
                        @can('user-profiles.view')
                            <a href="{{ route('cms.admin.user-profiles.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.user-profiles.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-id-card w-5 text-sky-400"></i>
                                {{ __('user-profiles.title') }}
                            </a>
                        @endcan
                        @can('roles.view')
                            <a href="{{ route('cms.admin.roles.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.roles.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-user-shield w-5 text-sky-400"></i>
                                {{ __('roles.title') }}
                            </a>
                        @endcan
                        @can('permissions.view')
                            <a href="{{ route('cms.admin.permissions.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.permissions.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-key w-5 text-sky-400"></i>
                                {{ __('permissions.title') }}
                            </a>
                        @endcan
                    </div>
                @endcanany

                @canany(['categories.view', 'courses.view', 'quizzes.view', 'live-sessions.view'])
                    <div class="space-y-1" role="group" aria-labelledby="nav-group-content">
                        <p id="nav-group-content" class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('sidebar.groups.content') }}</p>
                        @can('categories.view')
                            <a href="{{ route('cms.admin.categories.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.categories.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-folder-tree w-5 text-sky-400"></i>
                                {{ __('categories.title') }}
                            </a>
                        @endcan
                        @can('courses.view')
                            <a href="{{ route('cms.admin.courses.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.courses.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-graduation-cap w-5 text-sky-400"></i>
                                {{ __('courses.title') }}
                            </a>
                        @endcan
                        @can('quizzes.view')
                            <a href="{{ route('cms.admin.quizzes.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.quizzes.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-circle-question w-5 text-sky-400"></i>
                                {{ __('quizzes.title') }}
                            </a>
                        @endcan
                        @can('live-sessions.view')
                            <a href="{{ route('cms.admin.live-sessions.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.live-sessions.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-video w-5 text-sky-400"></i>
                                {{ __('live_sessions.title') }}
                            </a>
                        @endcan
                    </div>
                @endcanany

                @canany(['enrollments.view', 'quiz-attempts.view', 'progress.view', 'certificates.view', 'certificate-templates.view'])
                    <div class="space-y-1" role="group" aria-labelledby="nav-group-learning">
                        <p id="nav-group-learning" class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('sidebar.groups.learning') }}</p>
                        @can('enrollments.view')
                            <a href="{{ route('cms.admin.enrollments.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.enrollments.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-user-graduate w-5 text-sky-400"></i>
                                {{ __('enrollments.title') }}
                            </a>
                        @endcan
                        @can('quiz-attempts.view')
                            <a href="{{ route('cms.admin.quiz-attempts.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.quiz-attempts.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-clipboard-question w-5 text-sky-400"></i>
                                {{ __('quiz_attempts.title') }}
                            </a>
                        @endcan
                        @can('progress.view')
                            <a href="{{ route('cms.admin.progress.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.progress.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-chart-line w-5 text-sky-400"></i>
                                {{ __('progress.title') }}
                            </a>
                        @endcan
                        @can('certificates.view')
                            <a href="{{ route('cms.admin.certificates.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.certificates.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-award w-5 text-sky-400"></i>
                                {{ __('certificates.title') }}
                            </a>
                        @endcan
                        @can('certificate-templates.view')
                            <a href="{{ route('cms.admin.certificate-templates.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.certificate-templates.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-certificate w-5 text-sky-400"></i>
                                {{ __('certificate-templates.title') }}
                            </a>
                        @endcan
                    </div>
                @endcanany

                @canany(['orders.view', 'receipts.view', 'payments.view', 'coupons.view', 'carts.view'])
                    <div class="space-y-1" role="group" aria-labelledby="nav-group-sales">
                        <p id="nav-group-sales" class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('sidebar.groups.sales') }}</p>
                        @can('orders.view')
                            <a href="{{ route('cms.admin.orders.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.orders.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-receipt w-5 text-sky-400"></i>
                                {{ __('orders.title') }}
                            </a>
                        @endcan
                        @can('receipts.view')
                            <a href="{{ route('cms.admin.receipts.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.receipts.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-file-invoice-dollar w-5 text-sky-400"></i>
                                {{ __('receipts.title') }}
                            </a>
                        @endcan
                        @can('payments.view')
                            <a href="{{ route('cms.admin.payments.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.payments.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-credit-card w-5 text-sky-400"></i>
                                {{ __('payments.title') }}
                            </a>
                        @endcan
                        @can('coupons.view')
                            <a href="{{ route('cms.admin.coupons.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.coupons.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-ticket w-5 text-sky-400"></i>
                                {{ __('coupons.title') }}
                            </a>
                        @endcan
                        @can('carts.view')
                            <a href="{{ route('cms.admin.carts.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.carts.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-cart-shopping w-5 text-sky-400"></i>
                                {{ __('carts.title') }}
                            </a>
                        @endcan
                    </div>
                @endcanany

                @canany(['notifications.view', 'audit-logs.view'])
                    <div class="space-y-1" role="group" aria-labelledby="nav-group-system">
                        <p id="nav-group-system" class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('sidebar.groups.system') }}</p>
                        @can('notifications.view')
                            <a href="{{ route('cms.admin.notifications.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.notifications.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-bell w-5 text-sky-400"></i>
                                {{ __('notifications.title') }}
                            </a>
                        @endcan
                        @can('audit-logs.view')
                            <a href="{{ route('cms.admin.audit-logs.index') }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.audit-logs.*') ? 'bg-slate-800 text-white' : '' }}">
                                <i class="fa-solid fa-shield-halved w-5 text-sky-400"></i>
                                {{ __('audit_logs.title') }}
                            </a>
                        @endcan
                    </div>
                @endcanany
            </nav>

            <div class="shrink-0 border-t border-slate-800 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-900 p-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/20 font-semibold text-sky-300">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ auth()->user()->getRoleNames()->implode(', ') ?: '—' }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur-sm sm:px-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" data-sidebar-toggle
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm lg:hidden"
                            aria-label="Toggle navigation" aria-controls="cms-sidebar" aria-expanded="false">
                            <i class="fa-solid fa-bars"></i>
                        </button>
                        <button type="button" data-sidebar-collapse title="{{ __('cms.sidebar.toggle') }}" aria-label="{{ __('cms.sidebar.toggle') }}"
                            class="hidden h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 lg:inline-flex">
                            <i class="fa-solid fa-bars-staggered"></i>
                        </button>
                        <div>
                            <p data-cms-title-label class="text-xs uppercase tracking-[0.2em] text-slate-400">Overview</p>
                            <h2 class="truncate text-lg font-bold text-slate-900 sm:text-xl">@yield('title', 'Dashboard')</h2>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        @php $toLocale = app()->isLocale('ar') ? 'en' : 'ar'; @endphp
                        <form method="POST" action="{{ route('locale.update', $toLocale) }}">
                            @csrf
                            <button type="submit" lang="{{ $toLocale }}" title="{{ $toLocale === 'ar' ? 'العربية' : 'English' }}"
                                aria-label="{{ $toLocale === 'ar' ? 'العربية' : 'English' }}"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50">
                                <i class="fa-solid fa-globe"></i>
                            </button>
                        </form>
                        <button type="button" data-theme-toggle title="{{ __('student.nav.theme') }}" aria-label="{{ __('student.nav.theme') }}"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50">
                            <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                            <i class="fa-solid fa-sun" data-theme-icon="sun"></i>
                        </button>
                        <button type="button" data-cms-search-open title="{{ __('cms.search.title') }} (Ctrl+K)" aria-label="{{ __('cms.search.title') }}"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        @php $unreadCount = auth()->check() ? auth()->user()->unreadNotifications()->reorder()->count() : 0; @endphp
                        <div class="relative" id="cms-notif">
                            <button type="button" data-notif-toggle aria-haspopup="true" aria-expanded="false"
                                title="{{ __('notifications.mine.title') }}" aria-label="{{ __('notifications.mine.title') }}"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50">
                                <i class="fa-solid fa-bell"></i>
                                <span data-notif-badge @if ($unreadCount < 1) hidden @endif
                                    class="absolute -end-1 -top-1 min-w-[1.25rem] rounded-full bg-rose-500 px-1 text-center text-[11px] font-semibold leading-5 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                            </button>
                            <div data-notif-panel hidden
                                class="absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ __('notifications.mine.title') }}</p>
                                    <button type="button" data-notif-readall title="{{ __('notifications.mine.mark_all') }}"
                                        class="text-xs font-medium text-sky-600 hover:underline"><i class="fa-solid fa-check-double"></i></button>
                                </div>
                                <ul data-notif-list class="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm"></ul>
                                <a href="{{ route('cms.admin.my-notifications.index') }}"
                                    class="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-medium text-sky-600 hover:bg-slate-50">{{ __('cms.notif.view_all') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                @yield('main-content')
            </main>
        </div>
    </div>

    <div id="cms-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden" data-sidebar-backdrop>
    </div>

    <div id="cms-search" hidden class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-950/50 p-4 pt-24" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                <input type="search" data-cms-search-input autocomplete="off" placeholder="{{ __('cms.search.placeholder') }}"
                    class="h-12 w-full border-0 bg-transparent text-sm text-slate-800 outline-none focus:ring-0">
                <kbd class="hidden rounded border border-slate-200 px-1.5 text-[11px] text-slate-400 sm:inline">Esc</kbd>
            </div>
            <ul data-cms-search-list class="max-h-80 overflow-y-auto p-2 text-sm"></ul>
            <p data-cms-search-empty hidden class="px-4 py-6 text-center text-sm text-slate-400">{{ __('cms.search.empty') }}</p>
        </div>
    </div>

    <script>
    (function () {
        var $ = function (s, r) { return (r || document).querySelector(s); };
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        /* ---------- قائمة الجوال المنزلقة ---------- */
        var side = $('#cms-sidebar'), back = $('#cms-sidebar-backdrop'), burger = $('[data-sidebar-toggle]');
        function menu(open) {
            side.classList.toggle('hidden', !open);
            side.classList.toggle('flex', open);
            back.classList.toggle('hidden', !open);
            document.body.classList.toggle('cms-menu-open', open);
            if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (burger) burger.addEventListener('click', function () { menu(side.classList.contains('hidden')); });
        if (back) back.addEventListener('click', function () { menu(false); });
        side.addEventListener('click', function (e) { if (e.target.closest('a') && window.innerWidth < 1024) menu(false); });
        window.addEventListener('resize', function () { if (window.innerWidth >= 1024) menu(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menu(false); });

        /* ---------- إخفاء/إظهار القائمة الجانبية (للشاشات الكبيرة) ---------- */
        var col = $('[data-sidebar-collapse]');
        if (col) col.addEventListener('click', function () {
            var on = document.documentElement.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('cms-sidebar', on ? 'collapsed' : 'open'); } catch (e) {}
        });

        /* ---------- البحث: يفلتر صفحات القائمة الجانبية (المسموحة للمستخدم فقط) ---------- */
        var box = $('#cms-search'), input = $('[data-cms-search-input]'), list = $('[data-cms-search-list]'), empty = $('[data-cms-search-empty]');
        var pages = Array.prototype.map.call(document.querySelectorAll('#cms-sidebar nav a'), function (a) {
            var ic = a.querySelector('i');
            return { href: a.href, label: a.textContent.replace(/\s+/g, ' ').trim(), icon: ic ? ic.className.replace(/\bw-5\b|text-\S+/g, '').trim() : 'fa-solid fa-file' };
        });
        var active = 0;
        function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function render() {
            var q = input.value.trim().toLowerCase();
            var rows = pages.filter(function (p) { return !q || p.label.toLowerCase().indexOf(q) !== -1; });
            active = 0;
            list.innerHTML = rows.map(function (p, i) {
                return '<li><a href="' + esc(p.href) + '" data-i="' + i + '" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-slate-700 hover:bg-slate-100 ' + (i === 0 ? 'bg-slate-100' : '') + '"><i class="' + p.icon + ' w-5 text-sky-500"></i><span>' + esc(p.label) + '</span></a></li>';
            }).join('');
            empty.hidden = rows.length > 0;
        }
        function mark(n) {
            var items = list.querySelectorAll('a'); if (!items.length) return;
            active = (n + items.length) % items.length;
            items.forEach(function (a, i) { a.classList.toggle('bg-slate-100', i === active); });
            items[active].scrollIntoView({ block: 'nearest' });
        }
        function openSearch() { box.hidden = false; input.value = ''; render(); input.focus(); }
        function closeSearch() { box.hidden = true; }
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-cms-search-open]')) { openSearch(); return; }
            if (e.target === box) closeSearch();
        });
        input.addEventListener('input', render);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
            else if (e.key === 'Enter') { var a = list.querySelectorAll('a')[active]; if (a) { e.preventDefault(); window.location.href = a.href; } }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); box.hidden ? openSearch() : closeSearch(); }
            else if (e.key === 'Escape') { closeSearch(); closePanel(); }
        });

        /* ---------- الإشعارات: قائمة منسدلة + تحديث دوري للشارة ---------- */
        var wrap = $('#cms-notif'), toggle = $('[data-notif-toggle]', wrap), panel = $('[data-notif-panel]', wrap), nlist = $('[data-notif-list]', wrap);
        var url = @json(route('cms.admin.my-notifications.latest'));
        var readAllUrl = @json(route('cms.admin.my-notifications.read-all'));
        function badge(n) {
            document.querySelectorAll('[data-notif-badge]').forEach(function (b) { b.textContent = n > 99 ? '99+' : String(n); b.hidden = n < 1; });
        }
        function load(withList) {
            return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    badge(d.unread);
                    if (!withList) return;
                    nlist.innerHTML = d.items.length ? d.items.map(function (n) {
                        return '<li class="flex gap-3 px-4 py-3 ' + (n.read ? '' : 'bg-sky-50/60') + '"><span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600"><i class="fa-solid ' + esc(n.icon) + '"></i></span><div class="min-w-0"><p class="truncate font-medium text-slate-900">' + esc(n.title) + '</p><p class="line-clamp-2 text-xs text-slate-500">' + esc(n.body) + '</p><p class="mt-1 text-[11px] text-slate-400" dir="ltr">' + esc(n.time) + '</p></div></li>';
                    }).join('') : '<li class="px-4 py-8 text-center text-slate-400"><i class="fa-solid fa-bell-slash mb-2 block text-xl"></i>' + esc(@json(__('notifications.mine.empty_title'))) + '</li>';
                }).catch(function () {});
        }
        function closePanel() { if (!panel.hidden) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } }
        toggle.addEventListener('click', function () {
            var open = panel.hidden;
            panel.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) load(true);
        });
        document.addEventListener('click', function (e) { if (!e.target.closest('#cms-notif')) closePanel(); });
        $('[data-notif-readall]', wrap).addEventListener('click', function () {
            fetch(readAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function () { return load(true); });
        });
        setInterval(function () { if (!document.hidden) load(!panel.hidden); }, 60000);
    })();
    </script>

    @stack('scripts')
</body>

</html>
