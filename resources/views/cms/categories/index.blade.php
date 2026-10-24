@extends('cms.parent')

@section('title', __('categories.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/categories.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/categories.js')
@endpush

@section('main-content')
<div id="categoriesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.categories.index') }}"
     data-create-url="{{ route('cms.admin.categories.create') }}"
     data-store-url="{{ route('cms.admin.categories.store') }}"
     data-show-url="{{ route('cms.admin.categories.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.categories.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.categories.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.categories.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.categories.trashed') }}"
     data-restore-url="{{ route('cms.admin.categories.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.categories.force-delete', ':id') }}"
     data-i18n='@json(__("categories.js"))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-folder-tree', 'title' => __('categories.title'), 'subtitle' => __('categories.subtitle'),
        'home' => __('categories.breadcrumb.home'), 'group' => __('categories.breadcrumb.group'),
        'addLabel' => __('categories.actions.add'), 'trashLabel' => __('categories.actions.trash'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',         'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('categories.stats.total')],
        ['key' => 'roots',         'icon' => 'fa-sitemap',      'tone' => 'success', 'label' => __('categories.stats.roots')],
        ['key' => 'subs',          'icon' => 'fa-code-branch',  'tone' => 'info',    'label' => __('categories.stats.subs')],
        ['key' => 'with_children', 'icon' => 'fa-folder-open',  'tone' => 'primary', 'label' => __('categories.stats.with_children')],
        ['key' => 'trashed',       'icon' => 'fa-trash-can',    'tone' => 'danger',  'label' => __('categories.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('categories.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('categories.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <select data-filter="type" class="form-control filter-control">
            <option value="">{{ __('categories.filters.all_types') }}</option>
            <option value="root">{{ __('categories.filters.roots_only') }}</option>
            <option value="sub">{{ __('categories.filters.subs_only') }}</option>
        </select>
        <select data-filter="parent_id" class="form-control filter-control">
            <option value="">{{ __('categories.filters.all_parents') }}</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
            @endforeach
        </select>
        <label class="filter-date"><span>{{ __('categories.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('categories.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('categories.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.categories.partials.table', ['categories' => $categories])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.categories.create')
    @include('cms.categories.edit')
    @include('cms.categories.show')
    @include('cms.categories.softdelete')
</div>
@endsection