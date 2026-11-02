@extends('cms.parent')

@section('title', __('certificates.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/certificates.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/certificates.js')
@endpush

@section('main-content')
<div id="certificatesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.certificates.index') }}"
     data-create-url="{{ route('cms.admin.certificates.create') }}"
     data-store-url="{{ route('cms.admin.certificates.store') }}"
     data-show-url="{{ route('cms.admin.certificates.show', ':id') }}"
     data-eligible-url="{{ route('cms.admin.certificates.eligible') }}"
     data-revoke-url="{{ route('cms.admin.certificates.revoke', ':id') }}"
     data-reinstate-url="{{ route('cms.admin.certificates.reinstate', ':id') }}"
     data-download-url="{{ route('cms.admin.certificates.download', ':id') }}"
     data-i18n='@json(array_merge(__("certificates.js"), ["statuses" => __("certificates.statuses")]))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-award', 'title' => __('certificates.title'), 'subtitle' => __('certificates.subtitle'),
        'home' => __('certificates.breadcrumb.home'), 'group' => __('certificates.breadcrumb.group'),
        'addLabel' => __('certificates.actions.issue'),
        'canAdd' => auth()->user()->can('create', \App\Models\Certificate::class),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',      'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('certificates.stats.total')],
        ['key' => 'valid',      'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('certificates.stats.valid')],
        ['key' => 'revoked',    'icon' => 'fa-ban',          'tone' => 'danger',  'label' => __('certificates.stats.revoked')],
        ['key' => 'this_month', 'icon' => 'fa-calendar-check', 'tone' => 'info',  'label' => __('certificates.stats.this_month')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('certificates.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('certificates.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="status" class="form-control filter-control">
            <option value="">{{ __('certificates.filters.all_statuses') }}</option>
            <option value="valid">{{ __('certificates.statuses.valid') }}</option>
            <option value="revoked">{{ __('certificates.statuses.revoked') }}</option>
        </select>

        <select data-filter="course_id" class="form-control filter-control">
            <option value="">{{ __('certificates.filters.all_courses') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}">{{ $course->title_ar }} — {{ $course->title_en }}</option>
            @endforeach
        </select>

        <label class="filter-date"><span>{{ __('certificates.filters.from') }}</span>
            <input type="date" data-filter="from" class="form-control"></label>
        <label class="filter-date"><span>{{ __('certificates.filters.to') }}</span>
            <input type="date" data-filter="to" class="form-control"></label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('certificates.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.certificates.partials.table', ['certificates' => $certificates])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.certificates.create')
    @include('cms.certificates.show')
    @include('cms.certificates.revoke')
</div>
@endsection