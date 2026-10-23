@extends('cms.parent')

@section('title', __('permissions.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/permissions.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/permissions.js')
@endpush

@section('main-content')
<div id="permissionsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.permissions.index') }}"
     data-create-url="{{ route('cms.admin.permissions.create') }}"
     data-store-url="{{ route('cms.admin.permissions.store') }}"
     data-show-url="{{ route('cms.admin.permissions.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.permissions.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.permissions.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.permissions.destroy', ':id') }}"
     data-i18n='@json(__("permissions.js"))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-key', 'title' => __('permissions.title'), 'subtitle' => __('permissions.subtitle'),
        'home' => __('permissions.breadcrumb.home'), 'group' => __('permissions.breadcrumb.group'),
        'addLabel' => __('permissions.actions.add'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',  'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('permissions.stats.total')],
        ['key' => 'used',   'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('permissions.stats.used')],
        ['key' => 'unused', 'icon' => 'fa-circle-minus', 'tone' => 'danger',  'label' => __('permissions.stats.unused')],
        ['key' => 'roles',  'icon' => 'fa-user-shield',  'tone' => 'info',    'label' => __('permissions.stats.roles')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('permissions.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('permissions.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <select data-filter="role_id" class="form-control filter-control">
            <option value="">{{ __('permissions.filters.all_roles') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
        <select data-filter="guard_name" class="form-control filter-control">
            <option value="">{{ __('permissions.filters.all_guards') }}</option>
            @foreach ($guards as $guard)
                <option value="{{ $guard }}">{{ $guard }}</option>
            @endforeach
        </select>
        <label class="filter-date"><span>{{ __('permissions.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('permissions.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('permissions.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.permissions.partials.table', ['permissions' => $permissions])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.permissions.create')
    @include('cms.permissions.edit')
    @include('cms.permissions.show')
</div>
@endsection