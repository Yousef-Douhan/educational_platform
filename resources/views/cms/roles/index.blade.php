@extends('cms.parent')

@section('title', __('roles.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/roles.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/roles.js')
@endpush

@section('main-content')
<div id="rolesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.roles.index') }}"
     data-create-url="{{ route('cms.admin.roles.create') }}"
     data-store-url="{{ route('cms.admin.roles.store') }}"
     data-show-url="{{ route('cms.admin.roles.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.roles.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.roles.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.roles.destroy', ':id') }}"
     data-i18n='@json(__("roles.js"))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-user-shield', 'title' => __('roles.title'), 'subtitle' => __('roles.subtitle'),
        'home' => __('roles.breadcrumb.home'), 'group' => __('roles.breadcrumb.group'),
        'addLabel' => __('roles.actions.add'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',               'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('roles.stats.total')],
        ['key' => 'with_permissions',    'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('roles.stats.with_permissions')],
        ['key' => 'without_permissions', 'icon' => 'fa-circle-minus', 'tone' => 'danger',  'label' => __('roles.stats.without_permissions')],
        ['key' => 'permissions',         'icon' => 'fa-key',          'tone' => 'info',    'label' => __('roles.stats.permissions')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('roles.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('roles.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <select data-filter="guard_name" class="form-control filter-control">
            <option value="">{{ __('roles.filters.all_guards') }}</option>
            @foreach ($guards as $guard)
                <option value="{{ $guard }}">{{ $guard }}</option>
            @endforeach
        </select>
        <label class="filter-date"><span>{{ __('roles.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('roles.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('roles.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.roles.partials.table', ['roles' => $roles])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.roles.create')
    @include('cms.roles.edit')
    @include('cms.roles.show')
</div>
@endsection