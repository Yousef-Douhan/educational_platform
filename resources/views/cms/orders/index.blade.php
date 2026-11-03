@extends('cms.parent')

@section('title', __('orders.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/orders.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/orders.js')
@endpush

@section('main-content')
@php
    $i18n = array_merge(__('orders.js'), [
        'statuses'         => __('orders.statuses'),
        'gateways'         => __('orders.gateways'),
        'payment_statuses' => __('payments.statuses'),
    ]);
@endphp
<div id="ordersApp" class="crud-page"
     data-index-url="{{ route('cms.admin.orders.index') }}"
     data-show-url="{{ route('cms.admin.orders.show', ':id') }}"
     data-mark-paid-url="{{ route('cms.admin.orders.mark-paid', ':id') }}"
     data-cancel-url="{{ route('cms.admin.orders.cancel', ':id') }}"
     data-refund-url="{{ route('cms.admin.orders.refund', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-receipt', 'title' => __('orders.title'), 'subtitle' => __('orders.subtitle'),
        'home' => __('orders.breadcrumb.home'), 'group' => __('orders.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',    'icon' => 'fa-layer-group',    'tone' => 'primary', 'label' => __('orders.stats.total')],
        ['key' => 'pending',  'icon' => 'fa-hourglass-half', 'tone' => 'info',    'label' => __('orders.stats.pending')],
        ['key' => 'paid',     'icon' => 'fa-circle-check',   'tone' => 'success', 'label' => __('orders.stats.paid')],
        ['key' => 'refunded', 'icon' => 'fa-rotate-left',    'tone' => 'danger',  'label' => __('orders.stats.refunded')],
        ['key' => 'revenue',  'icon' => 'fa-coins',          'tone' => 'primary', 'label' => __('orders.stats.revenue')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('orders.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('orders.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control" aria-label="{{ __('orders.filters.all_statuses') }}">
            <option value="">{{ __('orders.filters.all_statuses') }}</option>
            @foreach (\App\Models\Order::STATUSES as $s)
                <option value="{{ $s }}">{{ __('orders.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('orders.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('orders.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('orders.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.orders.partials.table', ['orders' => $orders])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.orders.show')
    @include('cms.orders.action')
</div>
@endsection
