{{-- رأس الموقع الاحترافي: شريط علوي + رأس زجاجي لاصق. أنماط معزولة (.mh-*) تتبع الوضع الداكن والألوان العشرة --}}
@php
    $mhToLocale = app()->isLocale('ar') ? 'en' : 'ar';
    $mhLocaleTxt = $mhToLocale === 'ar' ? 'العربية' : 'English';
    $mhHome = request()->routeIs('student.home');
    $mhCourses = request()->routeIs('student.courses.index', 'student.courses.show');
    $mhMine = request()->routeIs('student.courses.mine');
    $mhMoreActive = request()->routeIs('student.certificates.*', 'student.live.*', 'student.notes.*', 'student.schedule.*', 'student.orders.*', 'certificates.verify.*');
@endphp

{{-- الشريط العلوي --}}
<div class="mh-top">
    <div class="mh-top-in">
        <p class="mh-tag"><i class="fa-solid fa-graduation-cap"></i> {{ __('student.home.tagline') }}</p>
        <div class="mh-tools">
            <form method="POST" action="{{ route('locale.update', $mhToLocale) }}" class="inline-flex">
                @csrf
                <button type="submit" class="mh-lang" lang="{{ $mhToLocale }}" title="{{ __('student.nav.language') }}" aria-label="{{ __('student.nav.language') }}"><i class="fa-solid fa-globe"></i>{{ $mhLocaleTxt }}</button>
            </form>
            <button type="button" data-theme-toggle class="mh-ib" title="{{ __('student.nav.theme') }}" aria-label="{{ __('student.nav.theme') }}">
                <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
                <i class="fa-solid fa-sun" data-theme-icon="sun"></i>
            </button>
            @guest
                <span class="mh-sep" aria-hidden="true"></span>
                <a href="{{ route('login') }}" class="mh-toplink">{{ __('student.nav.login') }}</a>
                <a href="{{ route('register') }}" class="mh-toplink cta">{{ __('student.nav.register') }}</a>
            @endguest
        </div>
    </div>
</div>

{{-- الرأس --}}
<header class="mh" id="mh">
    <div class="mh-in">
        <a href="{{ route('student.home') }}" class="mh-logo" aria-label="{{ __('brand.name') }}">@include('student.partials.logo', ['size' => 'lg'])</a>

        <nav class="mh-nav" aria-label="Main">
            <a href="{{ route('student.home') }}" class="mh-link {{ $mhHome ? 'active' : '' }}"><i class="fa-solid fa-house"></i>{{ __('student.nav.home') }}</a>
            <a href="{{ route('student.courses.index') }}" class="mh-link {{ $mhCourses ? 'active' : '' }}"><i class="fa-solid fa-book-open"></i>{{ __('student.nav.courses') }}</a>
            @if ($navCategories->isNotEmpty())
                <details class="mh-dd" data-mh-dd>
                    <summary class="mh-link"><i class="fa-solid fa-layer-group"></i>{{ __('student.nav.categories') }}<i class="fa-solid fa-chevron-down mh-chev"></i></summary>
                    <div class="mh-menu cats">
                        @foreach ($navCategories as $c)
                            <a href="{{ route('student.courses.index', ['category' => $c->slug]) }}"><span class="ic"><i class="fa-solid fa-folder-open"></i></span>{{ \App\Support\Loc::pick($c->name_ar, $c->name_en) }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
            @auth
                <a href="{{ route('student.courses.mine') }}" class="mh-link {{ $mhMine ? 'active' : '' }}"><i class="fa-solid fa-graduation-cap"></i>{{ __('student.nav.my_courses') }}</a>
            @endauth
            <details class="mh-dd" data-mh-dd>
                <summary class="mh-link {{ $mhMoreActive ? 'active' : '' }}"><i class="fa-solid fa-grip"></i>{{ __('student.nav.more') }}<i class="fa-solid fa-chevron-down mh-chev"></i></summary>
                <div class="mh-menu">
                    @auth
                        <a href="{{ route('student.certificates.index') }}"><span class="ic"><i class="fa-solid fa-award"></i></span>{{ __('student.nav.certificates') }}</a>
                        <a href="{{ route('student.live.index') }}"><span class="ic"><i class="fa-solid fa-video"></i></span>{{ __('student.nav.live_sessions') }}</a>
                        <a href="{{ route('student.notes.index') }}"><span class="ic"><i class="fa-solid fa-note-sticky"></i></span>{{ __('student.nav.notes') }}</a>
                        <a href="{{ route('student.schedule.index') }}"><span class="ic"><i class="fa-solid fa-calendar-week"></i></span>{{ __('student.nav.schedule') }}</a>
                        <a href="{{ route('student.orders.index') }}"><span class="ic"><i class="fa-solid fa-receipt"></i></span>{{ __('student.nav.orders') }}</a>
                    @endauth
                    <a href="{{ route('certificates.verify.form') }}"><span class="ic"><i class="fa-solid fa-shield-halved"></i></span>{{ __('student.nav.verify_certificate') }}</a>
                </div>
            </details>
        </nav>

        <div class="mh-actions">
            <form action="{{ route('student.courses.index') }}" method="GET" class="mh-search" role="search">
                <input type="search" name="q" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}" aria-label="{{ __('student.catalog.search_placeholder') }}">
                <i class="fa-solid fa-magnifying-glass"></i>
            </form>
            @auth
                <a href="{{ route('student.cart.show') }}" class="mh-ib" aria-label="{{ __('student.nav.cart') }}" title="{{ __('student.nav.cart') }}">
                    <i class="fa-solid fa-cart-shopping"></i>
                    @if ($cartCount > 0)<span class="mh-badge">{{ $cartCount }}</span>@endif
                </a>
                <a href="{{ route('student.notifications.index') }}" class="mh-ib" aria-label="{{ __('student.nav.notifications') }}" title="{{ __('student.nav.notifications') }}">
                    <i class="fa-solid fa-bell"></i>
                    @if ($unreadCount > 0)<span class="mh-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
                </a>
                @if (auth()->user()->isStaff())
                    <a href="{{ route('cms.admin.dashboard') }}" class="mh-btn admin mh-hide-md"><i class="fa-solid fa-gauge-high"></i>{{ __('student.nav.admin') }}</a>
                @endif
                <a href="{{ route('student.profile.edit') }}" class="mh-avatar" aria-label="{{ __('student.nav.profile') }}" title="{{ auth()->user()->name }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim(auth()->user()->name) ?: '?', 0, 1)) }}</a>
                <form method="POST" action="{{ route('logout') }}" class="mh-hide-md">
                    @csrf
                    <button class="mh-out" title="{{ __('student.nav.logout') }}"><i class="fa-solid fa-right-from-bracket"></i><span>{{ __('student.nav.logout') }}</span></button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mh-btn ghost mh-hide-md">{{ __('student.nav.login') }}</a>
                <a href="{{ route('register') }}" class="mh-btn solid mh-hide-md"><i class="fa-solid fa-user-plus"></i>{{ __('student.nav.register') }}</a>
            @endauth

            <details class="mh-burger" data-mh-dd>
                <summary class="mh-ib" aria-label="Menu"><i class="fa-solid fa-bars"></i><i class="fa-solid fa-xmark"></i></summary>
                <div class="mh-sheet">
                    <div class="grid">
                        <a href="{{ route('student.home') }}" class="{{ $mhHome ? 'active' : '' }}"><i class="fa-solid fa-house"></i>{{ __('student.nav.home') }}</a>
                        <a href="{{ route('student.courses.index') }}" class="{{ $mhCourses ? 'active' : '' }}"><i class="fa-solid fa-book-open"></i>{{ __('student.nav.courses') }}</a>
                        @auth
                            <a href="{{ route('student.courses.mine') }}" class="{{ $mhMine ? 'active' : '' }}"><i class="fa-solid fa-graduation-cap"></i>{{ __('student.nav.my_courses') }}</a>
                            <a href="{{ route('student.certificates.index') }}"><i class="fa-solid fa-award"></i>{{ __('student.nav.certificates') }}</a>
                            <a href="{{ route('student.live.index') }}"><i class="fa-solid fa-video"></i>{{ __('student.nav.live_sessions') }}</a>
                            <a href="{{ route('student.notes.index') }}"><i class="fa-solid fa-note-sticky"></i>{{ __('student.nav.notes') }}</a>
                            <a href="{{ route('student.schedule.index') }}"><i class="fa-solid fa-calendar-week"></i>{{ __('student.nav.schedule') }}</a>
                            <a href="{{ route('student.orders.index') }}"><i class="fa-solid fa-receipt"></i>{{ __('student.nav.orders') }}</a>
                            <a href="{{ route('student.profile.edit') }}"><i class="fa-solid fa-user"></i>{{ __('student.nav.profile') }}</a>
                        @endauth
                        <a href="{{ route('certificates.verify.form') }}"><i class="fa-solid fa-shield-halved"></i>{{ __('student.nav.verify_certificate') }}</a>
                    </div>
                    @if ($navCategories->isNotEmpty())
                        <p class="lbl">{{ __('student.nav.categories') }}</p>
                        <div class="grid">
                            @foreach ($navCategories as $c)
                                <a href="{{ route('student.courses.index', ['category' => $c->slug]) }}"><i class="fa-solid fa-folder-open"></i>{{ \App\Support\Loc::pick($c->name_ar, $c->name_en) }}</a>
                            @endforeach
                        </div>
                    @endif
                    <form action="{{ route('student.courses.index') }}" method="GET" class="s">
                        <input type="search" name="q" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}">
                    </form>
                    <div class="grid" style="margin-top:10px">
                        @auth
                            @if (auth()->user()->isStaff())
                                <a href="{{ route('cms.admin.dashboard') }}"><i class="fa-solid fa-gauge-high"></i>{{ __('student.nav.admin') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" style="display:contents">@csrf
                                <a href="#" role="button" onclick="this.closest('form').submit();return false;"><i class="fa-solid fa-right-from-bracket"></i>{{ __('student.nav.logout') }}</a>
                            </form>
                        @else
                            <a href="{{ route('login') }}"><i class="fa-solid fa-right-to-bracket"></i>{{ __('student.nav.login') }}</a>
                            <a href="{{ route('register') }}"><i class="fa-solid fa-user-plus"></i>{{ __('student.nav.register') }}</a>
                        @endauth
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>
@include('student.partials.asset', ['type' => 'js', 'file' => 'header'])
