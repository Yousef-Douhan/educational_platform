@extends('cms.parent')

@section('title', __('notifications.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/notifications.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/notifications.js')
@endpush

@php
    $i18n = __('notifications.js');
@endphp

@section('main-content')
<div id="notificationsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.notifications.index') }}"
     data-show-url="{{ route('cms.admin.notifications.show', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-bell', 'title' => __('notifications.title'), 'subtitle' => __('notifications.subtitle'),
        'home' => __('notifications.breadcrumb.home'), 'group' => __('notifications.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',  'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('notifications.stats.total')],
        ['key' => 'unread', 'icon' => 'fa-envelope',     'tone' => 'warning', 'label' => __('notifications.stats.unread')],
        ['key' => 'read',   'icon' => 'fa-envelope-open', 'tone' => 'info',   'label' => __('notifications.stats.read')],
        ['key' => 'today',  'icon' => 'fa-calendar-day', 'tone' => 'info',    'label' => __('notifications.stats.today')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('notifications.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('notifications.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="kind" class="form-control filter-control" aria-label="{{ __('notifications.filters.all_kinds') }}">
            <option value="">{{ __('notifications.filters.all_kinds') }}</option>
            @foreach (\App\Notifications\PlatformNotification::KINDS as $k)
                <option value="{{ $k }}">{{ __('notifications.kinds.' . $k . '.name') }}</option>
            @endforeach
        </select>

        <select data-filter="state" class="form-control filter-control" aria-label="{{ __('notifications.filters.all_states') }}">
            <option value="">{{ __('notifications.filters.all_states') }}</option>
            <option value="unread">{{ __('notifications.filters.unread') }}</option>
            <option value="read">{{ __('notifications.filters.read') }}</option>
        </select>

        <label class="filter-date"><span>{{ __('notifications.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('notifications.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('notifications.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.notifications.partials.table', ['records' => $records])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.notifications.show')
</div>
@endsection
