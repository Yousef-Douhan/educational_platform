@extends('cms.parent')

@section('title', __('coupons.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/coupons.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/coupons.js')
@endpush

@section('main-content')
<div id="couponsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.coupons.index') }}"
     data-create-url="{{ route('cms.admin.coupons.create') }}"
     data-store-url="{{ route('cms.admin.coupons.store') }}"
     data-show-url="{{ route('cms.admin.coupons.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.coupons.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.coupons.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.coupons.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.coupons.trashed') }}"
     data-restore-url="{{ route('cms.admin.coupons.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.coupons.force-delete', ':id') }}"
     data-toggle-url="{{ route('cms.admin.coupons.toggle', ':id') }}"
     data-i18n='@json(array_merge(__("coupons.js"), ["statuses" => __("coupons.statuses"), "types" => __("coupons.types")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-ticket', 'title' => __('coupons.title'), 'subtitle' => __('coupons.subtitle'),
        'home' => __('coupons.breadcrumb.home'), 'group' => __('coupons.breadcrumb.group'),
        'addLabel' => __('coupons.actions.add'), 'trashLabel' => __('coupons.actions.trash'),
        'canAdd' => auth()->user()->can('coupons.create'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',   'tone' => 'primary', 'label' => __('coupons.stats.total')],
        ['key' => 'active',    'icon' => 'fa-circle-check',  'tone' => 'success', 'label' => __('coupons.stats.active')],
        ['key' => 'expired',   'icon' => 'fa-hourglass-end', 'tone' => 'info',    'label' => __('coupons.stats.expired')],
        ['key' => 'exhausted', 'icon' => 'fa-battery-empty', 'tone' => 'info',    'label' => __('coupons.stats.exhausted')],
        ['key' => 'trashed',   'icon' => 'fa-trash-can',     'tone' => 'danger',  'label' => __('coupons.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('coupons.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('coupons.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control" aria-label="{{ __('coupons.filters.all_statuses') }}">
            <option value="">{{ __('coupons.filters.all_statuses') }}</option>
            @foreach (\App\Models\Coupon::STATUSES as $s)
                <option value="{{ $s }}">{{ __('coupons.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select data-filter="type" class="form-control filter-control" aria-label="{{ __('coupons.filters.all_types') }}">
            <option value="">{{ __('coupons.filters.all_types') }}</option>
            @foreach (\App\Models\Coupon::TYPES as $t)
                <option value="{{ $t }}">{{ __('coupons.types.' . $t) }}</option>
            @endforeach
        </select>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('coupons.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.coupons.partials.table', ['coupons' => $coupons])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.coupons.create')
    @include('cms.coupons.edit')
    @include('cms.coupons.show')
    @include('cms.coupons.softdelete')
</div>
@endsection
