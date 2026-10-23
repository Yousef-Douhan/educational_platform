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

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

</head>

<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="flex min-h-screen">
        <aside id="cms-sidebar"
            class="fixed inset-y-0 start-0 z-40 hidden w-72 shrink-0 border-e border-slate-800 bg-slate-950 text-slate-100 lg:static lg:flex lg:flex-col">
            <div class="flex items-center gap-3 border-b border-slate-800 px-6 py-5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-500/20 text-lg font-bold text-sky-300">
                    E
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Platform</p>
                    <h1 class="text-lg font-semibold">Educational CMS</h1>
                </div>
            </div>

            <nav class="flex-1 space-y-2 px-4 py-5">
                <a href="{{ route('cms.admin.dashboard') }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('cms.admin.dashboard') ? 'bg-slate-800 text-white' : '' }}">
                    <i class="fa-solid fa-gauge-high w-5 text-sky-400"></i>
                    Dashboard
                </a>
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

            </nav>

            <div class="border-t border-slate-800 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-900 p-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/20 font-semibold text-sky-300">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ auth()->user()->getRoleNames()->implode(', ') ?: '—' }}</p>
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
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Overview</p>
                            <h2 class="text-xl font-bold text-slate-900">@yield('title', 'Dashboard')</h2>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm sm:inline-flex">
                            <i class="fa-solid fa-magnifying-glass mr-2"></i>
                            Search
                        </button>
                        <button
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm">
                            <i class="fa-solid fa-bell"></i>
                        </button>
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

    @stack('scripts')
</body>

</html>
