@php
    use App\Support\Loc;
    $title = Loc::pick($course->title_ar, $course->title_en);
    $hasDiscount = $course->discount_price !== null && (float) $course->discount_price < (float) $course->price;
    $currency = config('app.currency', 'USD');
@endphp
@extends('student.layout')

@section('title', $title)

@section('content')
<div class="grid gap-8 lg:grid-cols-3">
    <div class="space-y-8 lg:col-span-2">
        <div>
            <p class="text-sm font-medium text-sky-700">{{ $course->category ? Loc::pick($course->category->name_ar, $course->category->name_en) : '' }}</p>
            <h1 class="mt-1 text-3xl font-extrabold text-slate-900">{{ $title }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ __('student.course.by') }} {{ $course->instructor?->name }}
                · {{ __('student.levels.' . $course->level) }}
                · {{ trans_choice('student.lessons_count', $stats['lessons'], ['count' => $stats['lessons']]) }}
                @if ($stats['minutes'] > 0) · {{ __('student.course.minutes', ['count' => $stats['minutes']]) }} @endif
            </p>
        </div>

        @php $description = Loc::pick($course->description_ar, $course->description_en); @endphp
        @if ($description !== '')
            <section>
                <h2 class="mb-2 text-lg font-bold text-slate-900">{{ __('student.course.about') }}</h2>
                <p class="whitespace-pre-line leading-7 text-slate-700">{{ $description }}</p>
            </section>
        @endif

        @if ($course->learningOutcomes->isNotEmpty())
            <section>
                <h2 class="mb-2 text-lg font-bold text-slate-900">{{ __('student.course.outcomes') }}</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($course->learningOutcomes as $item)
                        <li class="flex gap-2 text-sm text-slate-700"><i class="fa-solid fa-check mt-1 text-emerald-600"></i>
                            <span>{{ Loc::pick($item->outcome_ar ?? null, $item->outcome_en ?? null) }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($course->requirements->isNotEmpty())
            <section>
                <h2 class="mb-2 text-lg font-bold text-slate-900">{{ __('student.course.requirements') }}</h2>
                <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
                    @foreach ($course->requirements as $item)
                        <li>{{ Loc::pick($item->requirement_ar, $item->requirement_en) }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section>
            <h2 class="mb-3 text-lg font-bold text-slate-900">{{ __('student.course.curriculum') }}</h2>
            @forelse ($sections as $section)
                <div class="mb-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">{{ Loc::pick($section->title_ar, $section->title_en) }}</div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($section->lessons as $lesson)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm text-slate-700">
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid {{ ['video' => 'fa-circle-play', 'article' => 'fa-file-lines', 'document' => 'fa-paperclip', 'quiz' => 'fa-circle-question', 'live' => 'fa-video'][$lesson->content_type] ?? 'fa-circle' }} text-slate-400"></i>
                                    @if ($lesson->is_free_preview)
                                        <a class="font-medium text-indigo-700 hover:underline" href="{{ route('student.learn.lesson', [$course->slug, $lesson->id]) }}">{{ Loc::pick($lesson->title_ar, $lesson->title_en) }}</a>
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{{ __('student.course.preview') }}</span>
                                    @else
                                        {{ Loc::pick($lesson->title_ar, $lesson->title_en) }}
                                    @endif
                                </span>
                                @if ($lesson->duration_minutes)
                                    <span class="text-xs text-slate-400">{{ __('student.course.minutes', ['count' => $lesson->duration_minutes]) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('student.course.no_curriculum') }}</p>
            @endforelse
        </section>
    </div>

    <aside>
        <div class="sticky top-24 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @if ($course->image_src)
                <img src="{{ $course->image_src }}" alt="" class="aspect-video w-full rounded-xl object-cover">
            @endif

            <div class="text-2xl font-extrabold text-slate-900">
                @if ($course->is_free)
                    <span class="text-emerald-600">{{ __('student.free') }}</span>
                @else
                    {{ number_format((float) $course->final_price, 2) }} {{ $currency }}
                    @if ($hasDiscount)
                        <span class="ms-1 text-sm font-normal text-slate-400 line-through">{{ number_format((float) $course->price, 2) }}</span>
                    @endif
                @endif
            </div>

            @if ($state['teaches'])
                <p class="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">{{ __('student.course.you_teach') }}</p>
            @elseif ($state['enrolled'])
                <a href="{{ route('student.learn.course', $course->slug) }}" class="block rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-700">{{ __('student.learn.continue') }}</a>
            @elseif ($state['guest'])
                <a href="{{ route('student.courses.continue', $course->slug) }}" class="block rounded-xl {{ $course->is_free ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-sky-600 hover:bg-sky-700' }} px-4 py-3 text-center text-sm font-semibold text-white">
                    {{ $course->is_free ? __('student.course.enroll_free') : __('student.course.add_to_cart') }}
                </a>
                <p class="text-center text-xs text-slate-500">{{ __('student.course.login_required') }}</p>
                <a href="{{ route('register') }}" class="block text-center text-sm font-medium text-sky-700 hover:underline">{{ __('student.auth.create_account') }}</a>
            @elseif ($course->is_free)
                <form method="POST" action="{{ route('student.courses.enroll', $course->slug) }}">
                    @csrf
                    <button class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('student.course.enroll_free') }}</button>
                </form>
            @elseif ($state['in_cart'])
                <a href="{{ route('student.cart.show') }}" class="block rounded-xl bg-sky-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.course.in_cart') }}</a>
            @else
                <form method="POST" action="{{ route('student.cart.add') }}">
                    @csrf
                    <input type="hidden" name="course_id" value="{{ $course->id }}">
                    <button class="w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.course.add_to_cart') }}</button>
                </form>
            @endif
        </div>
    </aside>
</div>
@endsection
