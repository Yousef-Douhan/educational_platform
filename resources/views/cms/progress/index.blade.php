@extends('cms.parent')

@section('title', __('progress.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/progress.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/progress.js')
@endpush

@section('main-content')
@php
    $i18n = array_merge(__('progress.js'), [
        'enrollment_statuses' => __('progress.enrollment_statuses'),
        'types'               => __('progress.types'),
    ]);
@endphp
<div id="progressApp" class="crud-page"
     data-index-url="{{ route('cms.admin.progress.index') }}"
     data-show-url="{{ route('cms.admin.progress.show', ':id') }}"
     data-recalculate-url="{{ route('cms.admin.progress.recalculate', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-chart-line', 'title' => __('progress.title'), 'subtitle' => __('progress.subtitle'),
        'home' => __('progress.breadcrumb.home'), 'group' => __('progress.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',       'icon' => 'fa-layer-group',    'tone' => 'primary', 'label' => __('progress.stats.total')],
        ['key' => 'in_progress', 'icon' => 'fa-hourglass-half', 'tone' => 'info',    'label' => __('progress.stats.in_progress')],
        ['key' => 'finished',    'icon' => 'fa-circle-check',   'tone' => 'success', 'label' => __('progress.stats.finished')],
        ['key' => 'average',     'icon' => 'fa-chart-simple',   'tone' => 'primary', 'label' => __('progress.stats.average')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('progress.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('progress.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="state" class="form-control filter-control" aria-label="{{ __('progress.filters.all_states') }}">
            <option value="">{{ __('progress.filters.all_states') }}</option>
            <option value="in_progress">{{ __('progress.states.in_progress') }}</option>
            <option value="finished">{{ __('progress.states.finished') }}</option>
        </select>

        <select data-filter="course_id" class="form-control filter-control" aria-label="{{ __('progress.filters.all_courses') }}">
            <option value="">{{ __('progress.filters.all_courses') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}">{{ app()->getLocale() === 'ar' ? $course->title_ar : $course->title_en }}</option>
            @endforeach
        </select>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('progress.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.progress.partials.table', ['records' => $records])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.progress.show')
</div>
@endsection
