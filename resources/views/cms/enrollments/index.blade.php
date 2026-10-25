@extends('cms.parent')

@section('title', __('enrollments.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/enrollments.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/enrollments.js')
@endpush

@section('main-content')
<div id="enrollmentsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.enrollments.index') }}"
     data-create-url="{{ route('cms.admin.enrollments.create') }}"
     data-store-url="{{ route('cms.admin.enrollments.store') }}"
     data-show-url="{{ route('cms.admin.enrollments.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.enrollments.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.enrollments.update', ':id') }}"
     data-lookup-url="{{ route('cms.admin.enrollments.lookup-users') }}"
     data-i18n='@json(array_merge(__("enrollments.js"), ["statuses" => __("enrollments.statuses")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-user-graduate', 'title' => __('enrollments.title'), 'subtitle' => __('enrollments.subtitle'),
        'home' => __('enrollments.breadcrumb.home'), 'group' => __('enrollments.breadcrumb.group'),
        'addLabel' => __('enrollments.actions.add'),
        'canAdd' => auth()->user()->can('create', \App\Models\Enrollment::class),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('enrollments.stats.total')],
        ['key' => 'active',    'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('enrollments.stats.active')],
        ['key' => 'completed', 'icon' => 'fa-award',        'tone' => 'primary', 'label' => __('enrollments.stats.completed')],
        ['key' => 'expired',   'icon' => 'fa-hourglass-end','tone' => 'info',    'label' => __('enrollments.stats.expired')],
        ['key' => 'cancelled', 'icon' => 'fa-ban',          'tone' => 'danger',  'label' => __('enrollments.stats.cancelled')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('enrollments.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('enrollments.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control">
            <option value="">{{ __('enrollments.filters.all_statuses') }}</option>
            @foreach (\App\Models\Enrollment::STATUSES as $s)
                <option value="{{ $s }}">{{ __('enrollments.statuses.' . $s) }}</option>
            @endforeach
        </select>

        <select data-filter="course_id" class="form-control filter-control">
            <option value="">{{ __('enrollments.filters.all_courses') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}">{{ $course->title_ar }} — {{ $course->title_en }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('enrollments.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('enrollments.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('enrollments.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.enrollments.partials.table', ['enrollments' => $enrollments])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.enrollments.create')
    @include('cms.enrollments.edit')
    @include('cms.enrollments.show')
</div>
@endsection