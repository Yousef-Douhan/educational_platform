@extends('cms.parent')

@section('title', __('payments.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/payments.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/payments.js')
@endpush

@section('main-content')
<div id="paymentsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.payments.index') }}"
     data-show-url="{{ route('cms.admin.payments.show', ':id') }}"
     data-i18n='@json(array_merge(__("payments.js"), ["statuses" => __("payments.statuses")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-credit-card', 'title' => __('payments.title'), 'subtitle' => __('payments.subtitle'),
        'home' => __('payments.breadcrumb.home'), 'group' => __('payments.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',    'tone' => 'primary', 'label' => __('payments.stats.total')],
        ['key' => 'success',   'icon' => 'fa-circle-check',   'tone' => 'success', 'label' => __('payments.stats.success')],
        ['key' => 'pending',   'icon' => 'fa-hourglass-half', 'tone' => 'info',    'label' => __('payments.stats.pending')],
        ['key' => 'failed',    'icon' => 'fa-circle-xmark',   'tone' => 'danger',  'label' => __('payments.stats.failed')],
        ['key' => 'collected', 'icon' => 'fa-coins',          'tone' => 'primary', 'label' => __('payments.stats.collected')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('payments.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('payments.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control" aria-label="{{ __('payments.filters.all_statuses') }}">
            <option value="">{{ __('payments.filters.all_statuses') }}</option>
            @foreach (\App\Models\Payment::STATUSES as $s)
                <option value="{{ $s }}">{{ __('payments.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select data-filter="gateway" class="form-control filter-control" aria-label="{{ __('payments.filters.all_gateways') }}">
            <option value="">{{ __('payments.filters.all_gateways') }}</option>
            @foreach ($gateways as $g)
                <option value="{{ $g }}">{{ __('orders.gateways.' . $g) !== 'orders.gateways.' . $g ? __('orders.gateways.' . $g) : $g }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('payments.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('payments.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('payments.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.payments.partials.table', ['payments' => $payments])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.payments.show')
</div>
@endsection
