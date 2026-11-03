@extends('cms.parent')

@section('title', __('live_sessions.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/live-sessions.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/live-sessions.js')
@endpush

@php
    $i18n = array_merge(__('live_sessions.js'), [
        'phases' => __('live_sessions.phases'),
    ]);
@endphp

@section('main-content')
<div id="liveSessionsApp" class="crud-page"
     data-index-url="{{ route('cms.admin.live-sessions.index') }}"
     data-create-url="{{ route('cms.admin.live-sessions.create') }}"
     data-store-url="{{ route('cms.admin.live-sessions.store') }}"
     data-show-url="{{ route('cms.admin.live-sessions.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.live-sessions.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.live-sessions.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.live-sessions.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.live-sessions.trashed') }}"
     data-restore-url="{{ route('cms.admin.live-sessions.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.live-sessions.force-delete', ':id') }}"
     data-cancel-url="{{ route('cms.admin.live-sessions.cancel', ':id') }}"
     data-i18n='@json($i18n)'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-video', 'title' => __('live_sessions.title'), 'subtitle' => __('live_sessions.subtitle'),
        'home' => __('live_sessions.breadcrumb.home'), 'group' => __('live_sessions.breadcrumb.group'),
        'addLabel' => __('live_sessions.actions.add'), 'trashLabel' => __('live_sessions.actions.trash'),
        'canAdd' => auth()->user()->can('live-sessions.create'),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',     'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('live_sessions.stats.total')],
        ['key' => 'upcoming',  'icon' => 'fa-clock',        'tone' => 'info',    'label' => __('live_sessions.stats.upcoming')],
        ['key' => 'live',      'icon' => 'fa-tower-broadcast', 'tone' => 'success', 'label' => __('live_sessions.stats.live')],
        ['key' => 'ended',     'icon' => 'fa-flag-checkered', 'tone' => 'info',  'label' => __('live_sessions.stats.ended')],
        ['key' => 'trashed',   'icon' => 'fa-trash-can',    'tone' => 'danger',  'label' => __('live_sessions.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('live_sessions.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('live_sessions.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="phase" class="form-control filter-control" aria-label="{{ __('live_sessions.filters.all_phases') }}">
            <option value="">{{ __('live_sessions.filters.all_phases') }}</option>
            @foreach (\App\Models\LiveSession::PHASES as $p)
                <option value="{{ $p }}">{{ __('live_sessions.phases.' . $p) }}</option>
            @endforeach
        </select>

        <select data-filter="course_id" class="form-control filter-control" aria-label="{{ __('live_sessions.filters.all_courses') }}">
            <option value="">{{ __('live_sessions.filters.all_courses') }}</option>
            @foreach ($courses as $c)
                <option value="{{ $c->id }}">{{ app()->getLocale() === 'ar' ? $c->title_ar : $c->title_en }}</option>
            @endforeach
        </select>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('live_sessions.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.live-sessions.partials.table', ['records' => $records])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.live-sessions.create')
    @include('cms.live-sessions.edit')
    @include('cms.live-sessions.show')
    @include('cms.live-sessions.cancel')
    @include('cms.live-sessions.softdelete')
</div>
@endsection
