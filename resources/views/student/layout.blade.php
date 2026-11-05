@php
    $cartCount = auth()->check()
        ? \App\Models\CartItem::whereIn('cart_id', \App\Models\Cart::where('user_id', auth()->id())->select('id'))->count()
        : 0;
    $navCategories = \App\Models\Category::whereNull('parent_id')->orderBy('name_en')->limit(20)->get(['id', 'name_ar', 'name_en', 'slug']);
    $unreadCount = auth()->check() ? auth()->user()->unreadNotifications()->reorder()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('student.partials.theme-script')
    <title>@yield('title', __('student.nav.courses')) | {{ __('brand.name') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body{font-family:'Cairo',system-ui,sans-serif}details>summary{list-style:none}details>summary::-webkit-details-marker{display:none}</style>
    @include('student.partials.asset', ['file' => 'tokens'])
    @include('student.partials.asset', ['file' => 'header'])
    @stack('styles')
    @include('student.partials.motion')
    @include('student.partials.palette')
    @include('student.partials.pomodoro')
</head>
<body class="flex min-h-screen flex-col overflow-x-clip bg-slate-50 text-slate-800 antialiased">
    @include('student.partials.theme-toggle')
    @include('student.partials.header')

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
        <div class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">© {{ date('Y') }} {{ __('brand.name') }}</div>
    </footer>
</body>
</html>
