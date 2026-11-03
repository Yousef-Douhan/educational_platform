@extends('student.layout')

@section('title', __('student.nav.my_courses'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">{{ __('student.nav.my_courses') }}</h1>

    @if ($enrollments->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-slate-500">{{ __('student.mine.empty') }}</p>
            <a href="{{ route('student.courses.index') }}" class="mt-4 inline-block rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.mine.browse') }}</a>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($enrollments as $enrollment)
                @php
                    $course  = $enrollment->course;
                    $percent = (float) ($progress[$course->id]->progress_percentage ?? 0);
                @endphp
                <a href="{{ route('student.learn.course', $course->slug) }}" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="aspect-video bg-gradient-to-br from-sky-100 to-indigo-100">
                        @if ($course->image_src)
                            <img src="{{ $course->image_src }}" alt="" loading="lazy" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="space-y-3 p-4">
                        <h3 class="line-clamp-2 font-semibold text-slate-900">{{ \App\Support\Loc::pick($course->title_ar, $course->title_en) }}</h3>
                        <div>
                            <div class="mb-1 flex justify-between text-xs text-slate-500">
                                <span>{{ __('student.mine.progress') }}</span><span>{{ number_format($percent, 0) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(0, min(100, $percent)) }}%"></div>
                            </div>
                        </div>
                        @if ($enrollment->status === 'completed')
                            <p class="text-xs font-bold text-emerald-700"><i class="fa-solid fa-circle-check me-1"></i>{{ __('student.mine.completed') }}</p>
                        @endif
                        @if ($enrollment->expires_at)
                            <p class="text-xs text-slate-400">{{ __('student.mine.expires', ['date' => $enrollment->expires_at->format('Y-m-d')]) }}</p>
                        @endif
                        <span class="inline-block rounded-lg bg-indigo-700 px-4 py-2 text-xs font-bold text-white group-hover:bg-indigo-800">{{ $percent > 0 ? __('student.learn.continue') : __('student.learn.start') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
