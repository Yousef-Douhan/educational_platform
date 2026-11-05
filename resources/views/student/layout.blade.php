@php
    $cartCount = auth()->check()
        ? \App\Models\CartItem::whereIn('cart_id', \App\Models\Cart::where('user_id', auth()->id())->select('id'))->count()
        : 0;
    $navCategories = \App\Models\Category::whereNull('parent_id')->orderBy('name_en')->limit(12)->get(['id', 'name_ar', 'name_en', 'slug']);
    $unreadCount = auth()->check() ? auth()->user()->unreadNotifications()->reorder()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('student.partials.theme-script')
    <title>@yield('title', __('student.nav.courses')) | {{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body{font-family:'Cairo',system-ui,sans-serif}details>summary{list-style:none}details>summary::-webkit-details-marker{display:none}</style>
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col overflow-x-clip bg-slate-50 text-slate-800 antialiased">
    @include('student.partials.theme-toggle')
    {{-- الشريط العلوي --}}
    <div class="bg-slate-900 text-xs text-slate-300">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2">
            <p class="hidden sm:block"><i class="fa-solid fa-graduation-cap me-1 text-amber-400"></i> {{ __('student.home.tagline') }}</p>
            <div class="ms-auto flex items-center gap-3">
                @include('student.partials.controls', ['tone' => 'dark'])
                <a href="{{ route('student.courses.index') }}" class="hover:text-white">{{ __('student.nav.courses') }}</a>
                @guest
                    <a href="{{ route('login') }}" class="hover:text-white">{{ __('student.nav.login') }}</a>
                    <a href="{{ route('register') }}" class="font-semibold text-amber-400 hover:text-amber-300">{{ __('student.nav.register') }}</a>
                @endguest
            </div>
        </div>
    </div>

    {{-- الرأس --}}
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('student.home') }}" class="shrink-0" aria-label="{{ config('app.name') }}">
                @include('student.partials.logo', ['size' => 'lg'])
            </a>

            {{-- قائمة الجوال والشاشات المتوسطة (الروابط الرئيسية تختفي تحت xl) --}}
            <details class="relative xl:hidden">
                <summary class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-slate-700" aria-label="Menu"><i class="fa-solid fa-bars"></i></summary>
                <div class="absolute start-0 top-full z-40 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-2 text-sm font-semibold text-slate-700 shadow-xl">
                    <a href="{{ route('student.home') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.home') }}</a>
                    <a href="{{ route('student.courses.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.courses') }}</a>
                    @auth
                        <a href="{{ route('student.courses.mine') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.my_courses') }}</a>
                        <a href="{{ route('student.certificates.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.certificates') }}</a>
                        <a href="{{ route('student.live.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.live_sessions') }}</a>
                        <a href="{{ route('student.orders.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.orders') }}</a>
                        <a href="{{ route('student.notifications.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.notifications') }}</a>
                        <a href="{{ route('student.profile.edit') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.profile') }}</a>
                    @endauth
                    <a href="{{ route('certificates.verify.form') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100">{{ __('student.nav.verify_certificate') }}</a>
                    <form action="{{ route('student.courses.index') }}" method="GET" class="p-2">
                        <input type="search" name="q" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}" class="w-full rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                    </form>
                </div>
            </details>

            <nav class="hidden min-w-0 items-center gap-0.5 text-sm font-semibold text-slate-700 xl:flex">
                <a href="{{ route('student.home') }}" class="rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-slate-100 hover:text-indigo-700">{{ __('student.nav.home') }}</a>
                <a href="{{ route('student.courses.index') }}" class="rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-slate-100 hover:text-indigo-700">{{ __('student.nav.courses') }}</a>
                @if ($navCategories->isNotEmpty())
                    <details class="relative">
                        <summary class="cursor-pointer rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-slate-100 hover:text-indigo-700">{{ __('student.nav.categories') }} <i class="fa-solid fa-chevron-down ms-1 text-[10px]"></i></summary>
                        <div class="absolute start-0 top-full z-40 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                            @foreach ($navCategories as $c)
                                <a href="{{ route('student.courses.index', ['category' => $c->slug]) }}" class="block rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-indigo-50 hover:text-indigo-700">{{ \App\Support\Loc::pick($c->name_ar, $c->name_en) }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
                @auth
                    <a href="{{ route('student.courses.mine') }}" class="rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-slate-100 hover:text-indigo-700">{{ __('student.nav.my_courses') }}</a>
                @endauth
                {{-- روابط ثانوية في قائمة "المزيد" كي لا يفيض الرأس --}}
                <details class="relative">
                    <summary class="cursor-pointer rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-slate-100 hover:text-indigo-700">{{ __('student.nav.more') }} <i class="fa-solid fa-chevron-down ms-1 text-[10px]"></i></summary>
                    <div class="absolute start-0 top-full z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        @auth
                            <a href="{{ route('student.certificates.index') }}" class="block rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-indigo-50 hover:text-indigo-700">{{ __('student.nav.certificates') }}</a>
                            <a href="{{ route('student.live.index') }}" class="block rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-indigo-50 hover:text-indigo-700">{{ __('student.nav.live_sessions') }}</a>
                            <a href="{{ route('student.orders.index') }}" class="block rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-indigo-50 hover:text-indigo-700">{{ __('student.nav.orders') }}</a>
                        @endauth
                        <a href="{{ route('certificates.verify.form') }}" class="block rounded-lg whitespace-nowrap px-2.5 py-2 hover:bg-indigo-50 hover:text-indigo-700">{{ __('student.nav.verify_certificate') }}</a>
                    </div>
                </details>
            </nav>

            <div class="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm font-semibold">
                <form action="{{ route('student.courses.index') }}" method="GET" class="relative hidden 2xl:block">
                    <input type="search" name="q" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}"
                           class="w-52 rounded-full border border-slate-200 bg-slate-50 py-2 ps-4 pe-10 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute end-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                </form>
                @auth
                    <a href="{{ route('student.cart.show') }}" class="relative rounded-full bg-slate-100 px-3.5 py-2.5 text-slate-700 hover:bg-indigo-100" aria-label="{{ __('student.nav.cart') }}">
                        <i class="fa-solid fa-cart-shopping"></i>
                        @if ($cartCount > 0)
                            <span class="absolute -end-1 -top-1 min-w-[1.25rem] rounded-full bg-rose-500 px-1 text-center text-[11px] font-bold leading-5 text-white">{{ $cartCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('student.notifications.index') }}" class="relative rounded-full bg-slate-100 px-3.5 py-2.5 text-slate-700 hover:bg-indigo-100" aria-label="{{ __('student.nav.notifications') }}">
                        <i class="fa-solid fa-bell"></i>
                        @if ($unreadCount > 0)
                            <span class="absolute -end-1 -top-1 min-w-[1.25rem] rounded-full bg-rose-500 px-1 text-center text-[11px] font-bold leading-5 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('student.profile.edit') }}" class="rounded-full bg-slate-100 px-3.5 py-2.5 text-slate-700 hover:bg-indigo-100" aria-label="{{ __('student.nav.profile') }}" title="{{ auth()->user()->name }}">
                        <i class="fa-solid fa-user"></i>
                    </a>
                    @if (auth()->user()->isStaff())
                        <a href="{{ route('cms.admin.dashboard') }}" class="hidden rounded-full border border-indigo-200 px-4 py-2 text-indigo-700 hover:bg-indigo-50 sm:inline-block">{{ __('student.nav.admin') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="whitespace-nowrap rounded-full px-3 py-2 text-slate-600 hover:bg-slate-100">{{ __('student.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-full border border-slate-200 px-4 py-2 hover:bg-slate-50">{{ __('student.nav.login') }}</a>
                    <a href="{{ route('register') }}" class="hidden rounded-full bg-indigo-700 px-4 py-2 text-white shadow hover:bg-indigo-800 sm:inline-block">{{ __('student.nav.register') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto w-full max-w-7xl px-4 pt-6">
            @include('student.partials.alerts')
            @auth
                @unless (auth()->user()->hasVerifiedEmail() || request()->routeIs('verification.*'))
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p><i class="fa-solid fa-envelope me-2"></i>{{ __('student.verify.banner') }}</p>
                        <form method="POST" action="{{ route('verification.send') }}">@csrf
                            <button class="font-bold underline">{{ __('student.verify.resend') }}</button>
                        </form>
                    </div>
                @endunless
            @endauth
        </div>
        @if (trim($__env->yieldContent('fullwidth')) !== '')
            @yield('content')
        @else
            <div class="mx-auto w-full max-w-7xl px-4 pb-4 pt-4">@yield('content')</div>
        @endif
    </main>

    {{-- التذييل --}}
    <footer class="mt-16 bg-slate-900 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-3">
            <div>
                <a href="{{ route('student.home') }}" class="mb-4 inline-block">@include('student.partials.logo', ['dark' => true, 'size' => 'lg'])</a>
                <p class="text-sm leading-7 text-slate-400">{{ __('student.home.footer_about') }}</p>
            </div>
            <div>
                <p class="mb-3 font-bold text-white">{{ __('student.footer.quick_links') }}</p>
                <ul class="space-y-2 text-sm">
                    <li><a class="hover:text-white" href="{{ route('student.home') }}">{{ __('student.nav.home') }}</a></li>
                    <li><a class="hover:text-white" href="{{ route('student.courses.index') }}">{{ __('student.nav.courses') }}</a></li>
                    <li><a class="hover:text-white" href="{{ route('certificates.verify.form') }}">{{ __('student.nav.verify_certificate') }}</a></li>
                    @guest
                        <li><a class="hover:text-white" href="{{ route('login') }}">{{ __('student.nav.login') }}</a></li>
                        <li><a class="hover:text-white" href="{{ route('register') }}">{{ __('student.nav.register') }}</a></li>
                    @endguest
                </ul>
            </div>
            <div>
                <p class="mb-3 font-bold text-white">{{ __('student.nav.categories') }}</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($navCategories->take(5) as $c)
                        <li><a class="hover:text-white" href="{{ route('student.courses.index', ['category' => $c->slug]) }}">{{ \App\Support\Loc::pick($c->name_ar, $c->name_en) }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">© {{ date('Y') }} {{ config('app.name', 'Laravel') }}</div>
    </footer>
</body>
</html>
