@extends('cms.parent')

@section('title', __('courses.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/courses.css', 'resources/css/cms/curriculum.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/courses.js')
@endpush

@section('main-content')
<div id="coursesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.courses.index') }}"
     data-create-url="{{ route('cms.admin.courses.create') }}"
     data-store-url="{{ route('cms.admin.courses.store') }}"
     data-show-url="{{ route('cms.admin.courses.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.courses.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.courses.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.courses.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.courses.trashed') }}"
     data-restore-url="{{ route('cms.admin.courses.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.courses.force-delete', ':id') }}"
     data-i18n='@json(array_merge(__("courses.js"), ["levels" => __("courses.levels"), "statuses" => __("courses.statuses")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-graduation-cap', 'title' => __('courses.title'), 'subtitle' => __('courses.subtitle'),
        'home' => __('courses.breadcrumb.home'), 'group' => __('courses.breadcrumb.group'),
        'addLabel' => __('courses.actions.add'), 'trashLabel' => __('courses.actions.trash'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('courses.stats.total')],
        ['key' => 'published', 'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('courses.stats.published')],
        ['key' => 'drafts',    'icon' => 'fa-file-pen',     'tone' => 'info',    'label' => __('courses.stats.drafts')],
        ['key' => 'free',      'icon' => 'fa-gift',         'tone' => 'primary', 'label' => __('courses.stats.free')],
        ['key' => 'trashed',   'icon' => 'fa-trash-can',    'tone' => 'danger',  'label' => __('courses.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('courses.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('courses.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control">
            <option value="">{{ __('courses.filters.all_statuses') }}</option>
            @foreach (\App\Models\Course::STATUSES as $s)
                <option value="{{ $s }}">{{ __('courses.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select data-filter="level" class="form-control filter-control">
            <option value="">{{ __('courses.filters.all_levels') }}</option>
            @foreach (\App\Models\Course::LEVELS as $l)
                <option value="{{ $l }}">{{ __('courses.levels.' . $l) }}</option>
            @endforeach
        </select>

        <select data-filter="category_id" class="form-control filter-control">
            <option value="">{{ __('courses.filters.all_categories') }}</option>
            @foreach ($options['categories'] as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>

        <select data-filter="pricing" class="form-control filter-control">
            <option value="">{{ __('courses.filters.all_pricing') }}</option>
            <option value="free">{{ __('courses.filters.free_only') }}</option>
            <option value="paid">{{ __('courses.filters.paid_only') }}</option>
        </select>

        @if ($options['can_pick_instructor'])
            <select data-filter="instructor_id" class="form-control filter-control">
                <option value="">{{ __('courses.filters.all_instructors') }}</option>
                @foreach ($options['instructors'] as $ins)
                    <option value="{{ $ins->id }}">{{ $ins->name }}</option>
                @endforeach
            </select>
        @endif

        <label class="filter-date"><span>{{ __('courses.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('courses.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('courses.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.courses.partials.table', ['courses' => $courses])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.courses.create')
    @include('cms.courses.edit')
    @include('cms.courses.show')
    @include('cms.courses.softdelete')
    @include('cms.courses.curriculum.modal')
</div>
@endsection