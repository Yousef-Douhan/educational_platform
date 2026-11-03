@php
    use App\Support\Loc;
    $courseTitle = Loc::pick($course->title_ar, $course->title_en);
    $lessonTitle = Loc::pick($lesson->title_ar, $lesson->title_en);
    $icons = ['video' => 'fa-circle-play', 'article' => 'fa-file-lines', 'document' => 'fa-paperclip', 'quiz' => 'fa-circle-question', 'live' => 'fa-video'];
@endphp
@extends('student.layout')

@section('title', $lessonTitle)
@section('fullwidth', '1')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ $enrolled ? route('student.courses.mine') : route('student.courses.show', $course->slug) }}" class="text-sm font-semibold text-indigo-700 hover:underline">← {{ $enrolled ? __('student.nav.my_courses') : __('student.learn.back_to_course') }}</a>
            <h1 class="mt-1 text-xl font-extrabold text-slate-900">{{ $courseTitle }}</h1>
        </div>
        @if ($percent !== null)
            <div class="w-full sm:w-64">
                <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ __('student.mine.progress') }}</span><span>{{ number_format($percent, 0) }}%</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500" style="width: {{ max(0, min(100, $percent)) }}%"></div></div>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- المحتوى --}}
        <div class="space-y-5 lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @if ($lesson->content_type === 'video')
                    @if ($video && $video['type'] === 'iframe')
                        <div class="aspect-video bg-black">
                            <iframe src="{{ $video['src'] }}" class="h-full w-full" loading="lazy" allowfullscreen
                                    allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
                                    referrerpolicy="strict-origin-when-cross-origin" title="{{ $lessonTitle }}"></iframe>
                        </div>
                    @elseif ($video && $video['type'] === 'video')
                        <video class="aspect-video w-full bg-black" controls controlsList="nodownload" preload="metadata" src="{{ $video['src'] }}"></video>
                    @elseif ($video && $video['type'] === 'link')
                        <div class="p-8 text-center">
                            <a href="{{ $video['src'] }}" target="_blank" rel="noopener noreferrer nofollow" class="inline-block rounded-xl bg-indigo-700 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-800"><i class="fa-solid fa-up-right-from-square me-2"></i>{{ __('student.learn.open_video') }}</a>
                        </div>
                    @else
                        <p class="p-10 text-center text-slate-500">{{ __('student.learn.video_unavailable') }}</p>
                    @endif
                @endif

                <div class="space-y-4 p-6">
                    <h2 class="text-2xl font-extrabold text-slate-900">{{ $lessonTitle }}</h2>

                    @if ($lesson->content_type === 'article')
                        @php $body = Loc::pick($lesson->content_ar, $lesson->content_en); @endphp
                        <div class="whitespace-pre-line leading-8 text-slate-700">{{ $body !== '' ? $body : __('student.learn.article_empty') }}</div>
                    @elseif ($lesson->content_type === 'live')
                        <p class="rounded-xl bg-sky-50 p-4 text-sm text-sky-800"><i class="fa-solid fa-video me-2"></i>{{ __('student.learn.live_note') }}</p>
                    @elseif ($lesson->content_type === 'document' && $lesson->attachments->isEmpty())
                        <p class="text-sm text-slate-500">{{ __('student.learn.article_empty') }}</p>
                    @elseif ($lesson->content_type === 'quiz')
                        @if (! $enrolled)
                            <p class="text-sm text-slate-500">{{ __('student.learn.not_enrolled') }}</p>
                        @elseif ($quizState === null)
                            <p class="text-sm text-slate-500">{{ __('student.learn.no_quiz') }}</p>
                        @else
                            @php $q = $quizState['quiz']; @endphp
                            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-slate-500">{{ __('student.learn.pass_mark') }}</dt><dd class="mt-1 text-lg font-bold">{{ rtrim(rtrim(number_format((float) $q->pass_mark, 2), '0'), '.') }}%</dd></div>
                                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-slate-500">{{ __('student.learn.time_limit') }}</dt><dd class="mt-1 text-lg font-bold">{{ $q->time_limit_minutes > 0 ? __('student.course.minutes', ['count' => $q->time_limit_minutes]) : __('student.learn.no_limit') }}</dd></div>
                                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-slate-500">{{ __('student.learn.attempts') }}</dt><dd class="mt-1 text-lg font-bold">{{ $q->attempts_allowed > 0 ? $quizState['used'] . ' / ' . $q->attempts_allowed : __('student.learn.unlimited') }}</dd></div>
                            </dl>

                            @if ($quizState['passed'])
                                <p class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"><i class="fa-solid fa-circle-check me-2"></i>{{ __('student.learn.quiz_passed') }}</p>
                            @endif

                            @if ($quizState['open'])
                                <a href="{{ route('student.attempts.show', $quizState['open']->id) }}" class="inline-block rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-slate-900 hover:bg-amber-400">{{ __('student.learn.resume_quiz') }}</a>
                            @else
                                <form method="POST" action="{{ route('student.quizzes.start', $q->id) }}">
                                    @csrf
                                    <button class="rounded-xl bg-indigo-700 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ $quizState['used'] > 0 ? __('student.learn.retry_quiz') : __('student.learn.start_quiz') }}</button>
                                </form>
                            @endif

                            @if ($quizState['attempts']->isNotEmpty())
                                <div>
                                    <h3 class="mb-2 text-sm font-bold text-slate-700">{{ __('student.learn.past_attempts') }}</h3>
                                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                                        @foreach ($quizState['attempts'] as $a)
                                            <li class="flex items-center justify-between px-4 py-2">
                                                <a class="font-medium text-indigo-700 hover:underline" href="{{ route('student.attempts.show', $a->id) }}">#{{ $a->attempt_number }}</a>
                                                <span>
                                                    @if ($a->status === 'in_progress') {{ __('student.learn.in_progress') }}
                                                    @else {{ number_format((float) $a->score, 0) }}% · {{ $a->is_passed ? __('student.learn.result_passed') : __('student.learn.result_failed') }}
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($lesson->attachments->isNotEmpty())
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-slate-700">{{ __('student.learn.attachments') }}</h3>
                            @auth
                                <ul class="space-y-2">
                                    @foreach ($lesson->attachments as $file)
                                        <li><a href="{{ route('student.attachments.download', $file->id) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm hover:bg-slate-50">
                                            <span><i class="fa-solid fa-file-arrow-down me-2 text-indigo-600"></i>{{ $file->title }}</span>
                                            <span class="text-xs text-slate-400">{{ number_format(($file->file_size ?? 0) / 1024, 0) }} KB</span></a></li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500">{{ __('student.learn.login_for_files') }}</p>
                            @endauth
                        </div>
                    @endif
                </div>
            </div>

            {{-- الإكمال والتنقل --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-2">
                    @if ($prev && ($enrolled || $prev->is_free_preview))
                        <a href="{{ route('student.learn.lesson', [$course->slug, $prev->id]) }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ __('student.learn.prev') }}</a>
                    @endif
                    @if ($next && ($enrolled || $next->is_free_preview))
                        <a href="{{ route('student.learn.lesson', [$course->slug, $next->id]) }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ __('student.learn.next') }}</a>
                    @endif
                </div>

                @if ($enrolled && $lesson->content_type !== 'quiz')
                    @if (in_array($lesson->id, $done, true))
                        <form method="POST" action="{{ route('student.learn.uncomplete', [$course->slug, $lesson->id]) }}">
                            @csrf @method('DELETE')
                            <button class="rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fa-solid fa-check me-1"></i>{{ __('student.learn.mark_undone') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('student.learn.complete', [$course->slug, $lesson->id]) }}">
                            @csrf
                            <button class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">{{ __('student.learn.mark_done') }}</button>
                        </form>
                    @endif
                @elseif (! $enrolled)
                    <a href="{{ route('student.courses.continue', $course->slug) }}" class="rounded-xl bg-indigo-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-800">{{ $course->is_free ? __('student.course.enroll_free') : __('student.course.add_to_cart') }}</a>
                @endif
            </div>
        </div>

        {{-- المنهج --}}
        <aside class="h-fit overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-24">
            <p class="border-b border-slate-100 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800">{{ __('student.course.curriculum') }}</p>
            <div class="max-h-[70vh] overflow-y-auto">
                @foreach ($sections as $section)
                    <p class="bg-slate-50/60 px-4 py-2 text-xs font-bold text-slate-500">{{ Loc::pick($section->title_ar, $section->title_en) }}</p>
                    <ul>
                        @foreach ($section->lessons as $item)
                            @php $open = $enrolled || $item->is_free_preview; $isDone = in_array($item->id, $done, true); @endphp
                            <li>
                                @if ($open)
                                    <a href="{{ route('student.learn.lesson', [$course->slug, $item->id]) }}"
                                       class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-indigo-50 {{ $item->id === $lesson->id ? 'bg-indigo-50 font-bold text-indigo-800' : 'text-slate-700' }}">
                                @else
                                    <span class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-400">
                                @endif
                                    <i class="fa-solid {{ $isDone ? 'fa-circle-check text-emerald-500' : ($open ? ($icons[$item->content_type] ?? 'fa-circle') . ' text-slate-400' : 'fa-lock text-slate-300') }}"></i>
                                    <span class="flex-1">{{ Loc::pick($item->title_ar, $item->title_en) }}</span>
                                    @if ($item->duration_minutes) <span class="text-xs text-slate-400">{{ $item->duration_minutes }}′</span> @endif
                                @if ($open) </a> @else </span> @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </aside>
    </div>
</div>
@endsection
