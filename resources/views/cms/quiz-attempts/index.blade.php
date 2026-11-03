@extends('cms.parent')

@section('title', __('quiz_attempts.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/quiz-attempts.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/quiz-attempts.js')
@endpush

@section('main-content')
@php
    $i18n = array_merge(__('quiz_attempts.js'), [
        'statuses' => __('quiz_attempts.statuses'),
        'types'    => __('quiz_attempts.types'),
    ]);
@endphp
<div id="attemptsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.quiz-attempts.index') }}"
     data-show-url="{{ route('cms.admin.quiz-attempts.show', ':id') }}"
     data-void-url="{{ route('cms.admin.quiz-attempts.void', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-clipboard-question', 'title' => __('quiz_attempts.title'), 'subtitle' => __('quiz_attempts.subtitle'),
        'home' => __('quiz_attempts.breadcrumb.home'), 'group' => __('quiz_attempts.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',       'icon' => 'fa-layer-group',    'tone' => 'primary', 'label' => __('quiz_attempts.stats.total')],
        ['key' => 'in_progress', 'icon' => 'fa-hourglass-half', 'tone' => 'info',    'label' => __('quiz_attempts.stats.in_progress')],
        ['key' => 'passed',      'icon' => 'fa-circle-check',   'tone' => 'success', 'label' => __('quiz_attempts.stats.passed')],
        ['key' => 'failed',      'icon' => 'fa-circle-xmark',   'tone' => 'danger',  'label' => __('quiz_attempts.stats.failed')],
        ['key' => 'average',     'icon' => 'fa-chart-simple',   'tone' => 'primary', 'label' => __('quiz_attempts.stats.average')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('quiz_attempts.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('quiz_attempts.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control" aria-label="{{ __('quiz_attempts.filters.all_statuses') }}">
            <option value="">{{ __('quiz_attempts.filters.all_statuses') }}</option>
            @foreach (\App\Models\QuizAttempt::STATUSES as $s)
                <option value="{{ $s }}">{{ __('quiz_attempts.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select data-filter="result" class="form-control filter-control" aria-label="{{ __('quiz_attempts.filters.all_results') }}">
            <option value="">{{ __('quiz_attempts.filters.all_results') }}</option>
            <option value="passed">{{ __('quiz_attempts.results.passed') }}</option>
            <option value="failed">{{ __('quiz_attempts.results.failed') }}</option>
        </select>

        <select data-filter="quiz_id" class="form-control filter-control" aria-label="{{ __('quiz_attempts.filters.all_quizzes') }}">
            <option value="">{{ __('quiz_attempts.filters.all_quizzes') }}</option>
            @foreach ($quizzes as $quiz)
                <option value="{{ $quiz->id }}">{{ app()->getLocale() === 'ar' ? $quiz->title_ar : $quiz->title_en }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('quiz_attempts.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('quiz_attempts.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('quiz_attempts.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.quiz-attempts.partials.table', ['attempts' => $attempts])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.quiz-attempts.show')
    @include('cms.quiz-attempts.void')
</div>
@endsection
