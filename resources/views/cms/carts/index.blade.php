@extends('cms.parent')

@section('title', __('carts.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/carts.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/carts.js')
@endpush

@section('main-content')
<div id="cartsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.carts.index') }}"
     data-show-url="{{ route('cms.admin.carts.show', ':id') }}"
     data-clear-url="{{ route('cms.admin.carts.clear', ':id') }}"
     data-i18n='@json(array_merge(__("carts.js"), ["states" => __("carts.states"), "issues" => __("carts.issues")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-cart-shopping', 'title' => __('carts.title'), 'subtitle' => __('carts.subtitle'),
        'home' => __('carts.breadcrumb.home'), 'group' => __('carts.breadcrumb.group'),
        'addLabel' => '', 'canAdd' => false,
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('carts.stats.total')],
        ['key' => 'active',    'icon' => 'fa-cart-arrow-down', 'tone' => 'success', 'label' => __('carts.stats.active')],
        ['key' => 'abandoned', 'icon' => 'fa-hourglass-end', 'tone' => 'danger',  'label' => __('carts.stats.abandoned', ['days' => \App\Models\Cart::ABANDONED_AFTER_DAYS])],
        ['key' => 'empty',     'icon' => 'fa-cart-shopping', 'tone' => 'info',    'label' => __('carts.stats.empty')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('carts.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('carts.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="state" class="form-control filter-control" aria-label="{{ __('carts.filters.all_states') }}">
            <option value="">{{ __('carts.filters.all_states') }}</option>
            @foreach (\App\Models\Cart::STATES as $s)
                <option value="{{ $s }}">{{ __('carts.states.' . $s) }}</option>
            @endforeach
        </select>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('carts.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.carts.partials.table', ['carts' => $carts])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.carts.show')
</div>
@endsection
