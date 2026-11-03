@extends('cms.parent')

@section('title', __('notifications.mine.title'))

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
<div id="myNotificationsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.my-notifications.index') }}"
     data-read-url="{{ route('cms.admin.my-notifications.read', ':id') }}"
     data-read-all-url="{{ route('cms.admin.my-notifications.read-all') }}"
     data-destroy-url="{{ route('cms.admin.my-notifications.destroy', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-bell', 'title' => __('notifications.mine.title'), 'subtitle' => __('notifications.mine.subtitle'),
        'home' => __('notifications.breadcrumb.home'), 'group' => __('notifications.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',  'icon' => 'fa-layer-group', 'tone' => 'primary', 'label' => __('notifications.mine.stats.total')],
        ['key' => 'unread', 'icon' => 'fa-envelope',    'tone' => 'warning', 'label' => __('notifications.mine.stats.unread')],
    ]])

    <section class="surface-card filters-bar">
        <select data-filter="state" class="form-control filter-control" aria-label="{{ __('notifications.filters.all_states') }}">
            <option value="">{{ __('notifications.filters.all_states') }}</option>
            <option value="unread">{{ __('notifications.mine.only_unread') }}</option>
        </select>

        <button type="button" class="btn btn-light" data-action="read-all">
            <i class="fa-solid fa-check-double"></i> {{ __('notifications.mine.mark_all') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.notifications.partials.mine-list', ['records' => $records])
        </div>
        @include('cms.partials.table-skeleton')
    </section>
</div>
@endsection
