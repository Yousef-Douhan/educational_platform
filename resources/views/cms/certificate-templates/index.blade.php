@extends('cms.parent')

@section('title', __('certificate-templates.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/certificate-templates.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/certificate-templates.js')
@endpush

@section('main-content')
<div id="templatesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.certificate-templates.index') }}"
     data-create-url="{{ route('cms.admin.certificate-templates.create') }}"
     data-store-url="{{ route('cms.admin.certificate-templates.store') }}"
     data-show-url="{{ route('cms.admin.certificate-templates.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.certificate-templates.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.certificate-templates.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.certificate-templates.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.certificate-templates.trashed') }}"
     data-restore-url="{{ route('cms.admin.certificate-templates.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.certificate-templates.force-delete', ':id') }}"
     data-i18n='@json(__("certificate-templates.js"))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-certificate', 'title' => __('certificate-templates.title'), 'subtitle' => __('certificate-templates.subtitle'),
        'home' => __('certificate-templates.breadcrumb.home'), 'group' => __('certificate-templates.breadcrumb.group'),
        'addLabel' => __('certificate-templates.actions.add'), 'trashLabel' => __('certificate-templates.actions.trash'),
        'canAdd' => auth()->user()->can('certificate-templates.create'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',   'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('certificate-templates.stats.total')],
        ['key' => 'used',    'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('certificate-templates.stats.used')],
        ['key' => 'unused',  'icon' => 'fa-circle-minus', 'tone' => 'info',    'label' => __('certificate-templates.stats.unused')],
        ['key' => 'trashed', 'icon' => 'fa-trash-can',    'tone' => 'danger',  'label' => __('certificate-templates.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('certificate-templates.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('certificate-templates.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('certificate-templates.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.certificate-templates.partials.table', ['templates' => $templates])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.certificate-templates.create')
    @include('cms.certificate-templates.edit')
    @include('cms.certificate-templates.show')
    @include('cms.certificate-templates.softdelete')
</div>
@endsection