@extends('cms.parent')

@section('title', __('audit_logs.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/audit-logs.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/audit-logs.js')
@endpush

@php
    $i18n = array_merge(__('audit_logs.js'), [
        'types'  => __('audit_logs.types'),
        'events' => __('audit_logs.events'),
    ]);
@endphp

@section('main-content')
<div id="auditLogsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.audit-logs.index') }}"
     data-show-url="{{ route('cms.admin.audit-logs.show', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-shield-halved', 'title' => __('audit_logs.title'), 'subtitle' => __('audit_logs.subtitle'),
        'home' => __('audit_logs.breadcrumb.home'), 'group' => __('audit_logs.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',         'icon' => 'fa-layer-group',          'tone' => 'primary', 'label' => __('audit_logs.stats.total')],
        ['key' => 'today',         'icon' => 'fa-calendar-day',         'tone' => 'info',    'label' => __('audit_logs.stats.today')],
        ['key' => 'week',          'icon' => 'fa-calendar-week',        'tone' => 'info',    'label' => __('audit_logs.stats.week')],
        ['key' => 'deletions',     'icon' => 'fa-trash-can',            'tone' => 'danger',  'label' => __('audit_logs.stats.deletions')],
        ['key' => 'failed_logins', 'icon' => 'fa-user-lock',            'tone' => 'danger',  'label' => __('audit_logs.stats.failed_logins')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('audit_logs.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('audit_logs.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="type" class="form-control filter-control" aria-label="{{ __('audit_logs.filters.all_types') }}">
            <option value="">{{ __('audit_logs.filters.all_types') }}</option>
            @foreach (\App\Services\AuditLogger::typeKeys() as $t)
                <option value="{{ $t }}">{{ __('audit_logs.types.' . $t) }}</option>
            @endforeach
        </select>

        <select data-filter="event" class="form-control filter-control" aria-label="{{ __('audit_logs.filters.all_events') }}">
            <option value="">{{ __('audit_logs.filters.all_events') }}</option>
            @foreach (\App\Services\AuditLogger::EVENTS as $e)
                <option value="{{ $e }}">{{ __('audit_logs.events.' . $e) }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('audit_logs.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('audit_logs.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('audit_logs.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.audit-logs.partials.table', ['records' => $records])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.audit-logs.show')
</div>
@endsection
