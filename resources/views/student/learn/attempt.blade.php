@php
    use App\Support\Loc;
    $lesson = $quiz->lesson;
    $course = $lesson->section->course;
    $back   = route('student.learn.lesson', [$course->slug, $lesson->id]);
    $title  = Loc::pick($quiz->title_ar, $quiz->title_en) ?: Loc::pick($lesson->title_ar, $lesson->title_en);
@endphp
@extends('student.layout')

@section('title', $title)

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ $back }}" class="text-sm font-semibold text-indigo-700 hover:underline">← {{ __('student.learn.back_to_lesson') }}</a>
    <h1 class="mb-1 mt-2 text-2xl font-extrabold text-slate-900">{{ $title }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('student.learn.attempt_no', ['n' => $attempt->attempt_number]) }}</p>

    @if ($attempt->status === 'in_progress')
        <form method="POST" action="{{ route('student.attempts.submit', $attempt->id) }}" id="quiz-form" class="space-y-5">
            @csrf
            @if ($data['remaining_seconds'] !== null)
                <div class="sticky top-20 z-10 rounded-xl bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800 ring-1 ring-amber-200">
                    {{ __('student.learn.remaining') }}: <span id="quiz-timer" data-seconds="{{ $data['remaining_seconds'] }}">--:--</span>
                </div>
            @endif

            @foreach ($data['questions'] as $i => $question)
                <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <legend class="px-1 text-xs font-bold text-slate-400">{{ $i + 1 }} / {{ count($data['questions']) }} · {{ $question['points'] }} {{ __('student.learn.points') }}</legend>
                    <p class="mb-3 whitespace-pre-line font-semibold text-slate-900">{{ $question['text'] }}</p>

                    @if ($question['type'] === 'text')
                        <input type="text" name="answers[{{ $question['id'] }}]" maxlength="1000"
                               value="{{ old('answers.' . $question['id'], $question['answer']['text'] ?? '') }}"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" autocomplete="off">
                    @else
                        <div class="space-y-2">
                            @foreach ($question['options'] as $option)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm hover:bg-indigo-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                    <input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $option['id'] }}"
                                           @checked((string) old('answers.' . $question['id'], $question['answer']['option_id'] ?? '') === (string) $option['id'])>
                                    <span>{{ $option['text'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
            @endforeach

            <button class="w-full rounded-xl bg-indigo-700 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.learn.submit_quiz') }}</button>
        </form>

        @if ($data['remaining_seconds'] !== null)
            <script>
                (function () {
                    var el = document.getElementById('quiz-timer'), form = document.getElementById('quiz-form');
                    var left = parseInt(el.dataset.seconds, 10), sent = false;
                    function tick() {
                        if (left <= 0) { if (!sent) { sent = true; form.submit(); } return; }
                        var m = Math.floor(left / 60), s = left % 60;
                        el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                        left--; setTimeout(tick, 1000);
                    }
                    form.addEventListener('submit', function () { sent = true; });
                    tick();
                })();
            </script>
        @endif
    @else
        <div class="rounded-2xl border bg-white p-8 text-center shadow-sm {{ $attempt->is_passed ? 'border-emerald-300' : 'border-rose-300' }}">
            <p class="text-5xl font-extrabold {{ $attempt->is_passed ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format((float) $attempt->score, 0) }}%</p>
            <p class="mt-2 text-lg font-bold">{{ $attempt->is_passed ? __('student.learn.result_passed') : __('student.learn.result_failed') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $attempt->points_earned }} / {{ $attempt->points_total }} {{ __('student.learn.points') }} · {{ __('student.learn.pass_mark') }}: {{ rtrim(rtrim(number_format((float) $attempt->pass_mark, 2), '0'), '.') }}%</p>
            @if ($attempt->status === 'expired')
                <p class="mt-3 text-xs text-amber-700">{{ __('student.learn.time_up_note') }}</p>
            @endif
            <a href="{{ $back }}" class="mt-6 inline-block rounded-xl bg-indigo-700 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.learn.back_to_lesson') }}</a>
        </div>
    @endif
</div>
@endsection
