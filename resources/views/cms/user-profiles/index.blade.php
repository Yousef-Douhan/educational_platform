@extends('cms.parent')

@section('title', __('user-profiles.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/user-profiles.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/user-profiles.js')
@endpush

@section('main-content')
@php
    $statCards = [
        ['key' => 'total',       'icon' => 'fa-layer-group',    'tone' => 'primary'],
        ['key' => 'active',      'icon' => 'fa-circle-check',   'tone' => 'success'],
        ['key' => 'with_avatar', 'icon' => 'fa-image',          'tone' => 'info'],
        ['key' => 'trashed',     'icon' => 'fa-trash-can',      'tone' => 'danger'],
    ];
@endphp

<div id="userProfilesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.user-profiles.index') }}"
     data-create-url="{{ route('cms.admin.user-profiles.create') }}"
     data-store-url="{{ route('cms.admin.user-profiles.store') }}"
     data-show-url="{{ route('cms.admin.user-profiles.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.user-profiles.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.user-profiles.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.user-profiles.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.user-profiles.trashed') }}"
     data-restore-url="{{ route('cms.admin.user-profiles.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.user-profiles.force-delete', ':id') }}"
     data-i18n='@json(__("user-profiles.js"))'>

    {{-- A. Page Header --}}
    <header class="page-header">
        <div class="page-header__main">
            <div class="page-header__icon"><i class="fa-solid fa-id-card"></i></div>
            <div>
                <ol class="breadcrumb-nav">
                    <li>{{ __('user-profiles.breadcrumb.home') }}</li>
                    <li>{{ __('user-profiles.breadcrumb.group') }}</li>
                    <li aria-current="page">{{ __('user-profiles.title') }}</li>
                </ol>
                <h1>{{ __('user-profiles.title') }}</h1>
                <p>{{ __('user-profiles.subtitle') }}</p>
            </div>
        </div>
        <div class="page-header__actions">
            <button type="button" class="btn btn-light" data-action="open-trash">
                <i class="fa-solid fa-trash-can-arrow-up"></i> {{ __('user-profiles.actions.trash') }}
            </button>
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('user-profiles.actions.add') }}
            </button>
        </div>
    </header>

    {{-- B. Statistics --}}
    <section class="stats-grid">
        @foreach ($statCards as $card)
            <article class="stat-card stat-card--{{ $card['tone'] }}">
                <div class="stat-card__icon"><i class="fa-solid {{ $card['icon'] }}"></i></div>
                <div>
                    <div class="stat-card__value" data-stat="{{ $card['key'] }}">{{ $stats[$card['key']] }}</div>
                    <div class="stat-card__label">{{ __('user-profiles.stats.' . $card['key']) }}</div>
                </div>
            </article>
        @endforeach
    </section>

    {{-- C. Search & Filters --}}
    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="filterSearch" class="form-control"
                   placeholder="{{ __('user-profiles.filters.search_placeholder') }}" autocomplete="off">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('user-profiles.actions.clear') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <select id="filterUser" class="form-control filter-control">
            <option value="">{{ __('user-profiles.filters.all_users') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
        <label class="filter-date">
            <span>{{ __('user-profiles.filters.from') }}</span>
            <input type="date" id="filterFrom" class="form-control">
        </label>
        <label class="filter-date">
            <span>{{ __('user-profiles.filters.to') }}</span>
            <input type="date" id="filterTo" class="form-control">
        </label>
        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('user-profiles.actions.reset') }}
        </button>
    </section>

    {{-- D. Data Table --}}
    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.user-profiles.partials.table', ['profiles' => $profiles])
        </div>
        <div class="table-skeleton" aria-hidden="true">
            @for ($i = 0; $i < 5; $i++)
                <div class="skeleton-row">
                    <span class="skeleton sk-circle"></span>
                    <span class="skeleton sk-line sk-w-30"></span>
                    <span class="skeleton sk-line sk-w-20"></span>
                    <span class="skeleton sk-line sk-w-20"></span>
                </div>
            @endfor
        </div>
    </section>

    @include('cms.user-profiles.create')
    @include('cms.user-profiles.edit')
    @include('cms.user-profiles.show')
    @include('cms.user-profiles.softdelete')
</div>
@endsection