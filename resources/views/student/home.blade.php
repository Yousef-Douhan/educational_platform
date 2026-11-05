@extends('student.layout')

@section('title', __('student.nav.home'))
@section('fullwidth', '1')

@section('content')
    @push('styles')
        @include('student.partials.asset', ['file' => 'home'])
    @endpush
    {{-- البطل --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-800 to-slate-900 text-white">
        <div class="pointer-events-none absolute -end-24 -top-24 h-96 w-96 rounded-full bg-amber-400/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 start-0 h-96 w-96 rounded-full bg-sky-400/20 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="mb-4 inline-block rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold text-amber-300 ring-1 ring-white/20">{{ __('student.home.tagline') }}</span>
                <h1 class="text-4xl font-extrabold leading-tight sm:text-5xl">{{ __('student.home.title') }}</h1>
                <p class="mt-4 max-w-xl text-lg leading-8 text-indigo-100">{{ __('student.home.subtitle') }}</p>
                <form action="{{ route('student.courses.index') }}" method="GET" class="mt-8 flex max-w-xl overflow-hidden rounded-full bg-white p-1.5 shadow-2xl">
                    <input type="search" name="q" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}"
                           class="w-full border-0 bg-transparent px-5 py-2.5 text-sm text-slate-900 focus:outline-none">
                    <button class="rounded-full bg-amber-400 px-7 py-2.5 text-sm font-extrabold text-slate-900 hover:bg-amber-300">{{ __('student.catalog.search') }}</button>
                </form>
                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('student.courses.index') }}" class="rounded-full border border-white/30 px-5 py-2 font-semibold hover:bg-white/10">{{ __('student.home.browse_all') }}</a>
                    @guest<a href="{{ route('register') }}" class="rounded-full px-5 py-2 font-semibold text-amber-300 hover:text-amber-200">{{ __('student.auth.create_account') }} →</a>@endguest
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 text-center">
                @foreach ([['courses', 'fa-book-open', 'student.home.stat_courses'], ['instructors', 'fa-chalkboard-user', 'student.home.stat_instructors'], ['students', 'fa-users', 'student.home.stat_students']] as [$key, $icon, $label])
                    <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur">
                        <i class="fa-solid {{ $icon }} mb-3 text-2xl text-amber-300"></i>
                        <p class="text-3xl font-extrabold">{{ number_format($stats[$key]) }}</p>
                        <p class="mt-1 text-xs text-indigo-200">{{ __($label) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-16 px-4 py-12">
        {{-- التصنيفات --}}
        @if ($categories->isNotEmpty())
            @php
                $cgHues = config('ui.card_hues');
                $cgIcons = config('ui.category_icons');
            @endphp
            <section class="cg" aria-labelledby="cg-title">
                <div class="cg-head">
                    <div>
                        <span class="cg-eyebrow"><i class="fa-solid fa-compass"></i> {{ __('student.home.cat_eyebrow') }}</span>
                        <h2 id="cg-title">{{ __('student.home.categories') }}</h2>
                        <p>{{ __('student.home.cat_sub') }}</p>
                    </div>
                    <a href="{{ route('student.courses.index') }}" class="cg-all">{{ __('student.home.all') }}<i class="fa-solid fa-arrow-left"></i></a>
                </div>
                <div class="cg-grid">
                    @foreach ($categories as $category)
                        @php
                            $hue = $cgHues[$loop->index % count($cgHues)];
                            $raw = trim((string) $category->icon);
                            $icon = preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', $raw) ? $raw
                                : (preg_match('/^fa-[a-z0-9-]+$/', $raw) ? 'fa-solid '.$raw : 'fa-solid '.$cgIcons[$loop->index % count($cgIcons)]);
                            $n = (int) $category->courses_total;
                        @endphp
                        <a href="{{ route('student.courses.index', ['category' => $category->slug]) }}" class="cg-card" style="--cg-a: {{ $hue[0] }}; --cg-b: {{ $hue[1] }}; --cg-i: {{ $loop->index }}">
                            <i class="fa-solid {{ explode(' ', $icon)[1] ?? 'fa-layer-group' }} cg-water" aria-hidden="true"></i>
                            <span class="cg-ic"><i class="{{ $icon }}"></i></span>
                            <span class="cg-name">{{ \App\Support\Loc::pick($category->name_ar, $category->name_en) }}</span>
                            <span class="cg-count">{{ trans_choice('student.home.cat_courses', $n, ['count' => $n]) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- أحدث الدورات --}}
        <section>
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold text-slate-900">{{ __('student.home.latest') }}</h2>
                <a href="{{ route('student.courses.index') }}" class="text-sm font-bold text-indigo-700 hover:underline">{{ __('student.home.all') }}</a>
            </div>
            @if ($latest->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('student.catalog.empty') }}</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($latest as $course)
                        @include('student.partials.course-card', ['course' => $course])
                    @endforeach
                </div>
            @endif
        </section>

        {{-- المزايا --}}
        <section class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200 sm:p-12">
            <h2 class="mb-8 text-center text-2xl font-extrabold text-slate-900">{{ __('student.home.why') }}</h2>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['fa-video', 'f1'], ['fa-hand-pointer', 'f2'], ['fa-file-circle-check', 'f3'], ['fa-user-graduate', 'f4']] as [$icon, $k])
                    <div class="text-center">
                        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-2xl text-amber-600"><i class="fa-solid {{ $icon }}"></i></span>
                        <h3 class="font-bold text-slate-900">{{ __('student.home.features.' . $k . '_title') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('student.home.features.' . $k . '_text') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- المدرّسون --}}
        @if ($instructors->isNotEmpty())
            @php $inHues = config('ui.card_hues'); @endphp
            <section class="in" aria-labelledby="in-title">
                <div class="in-head">
                    <span class="in-eyebrow"><i class="fa-solid fa-chalkboard-user"></i> {{ __('student.home.inst_eyebrow') }}</span>
                    <h2 id="in-title">{{ __('student.home.trainers') }}</h2>
                    <p>{{ __('student.home.inst_sub') }}</p>
                </div>
                <div class="in-grid">
                    @foreach ($instructors as $i)
                        @php
                            $hue = $inHues[$loop->index % count($inHues)];
                            $photo = $i->profile?->avatar_url;
                            $n = (int) $i->published_courses;
                        @endphp
                        <article class="in-card" style="--in-a: {{ $hue[0] }}; --in-b: {{ $hue[1] }}; --in-i: {{ $loop->index }}">
                            <div class="in-cover" aria-hidden="true"></div>
                            <div class="in-photo">
                                @if ($photo)
                                    <img src="{{ $photo }}" alt="{{ $i->name }}" loading="lazy" width="112" height="112">
                                @else
                                    <span>{{ mb_strtoupper(mb_substr(trim($i->name), 0, 1)) }}</span>
                                @endif
                            </div>
                            <h3 class="in-name">{{ $i->name }}</h3>
                            @if (filled($i->profile?->bio))
                                <p class="in-bio">{{ \Illuminate\Support\Str::limit($i->profile->bio, 90) }}</p>
                            @endif
                            <span class="in-count"><i class="fa-solid fa-book-open"></i>{{ trans_choice('student.home.cat_courses', $n, ['count' => $n]) }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
