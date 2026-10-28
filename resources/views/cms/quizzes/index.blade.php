@extends('cms.parent')

@section('title', __('quizzes.title'))

@push('styles')
    @vite(['resources/css/cms/crud-common.css', 'resources/css/cms/quizzes.css'])
@endpush

@push('scripts')
    @vite('resources/js/cms/quizzes.js')
@endpush

@section('main-content')
<div id="quizzesApp" class="crud-page"
     data-index-url="{{ route('cms.admin.quizzes.index') }}"
     data-create-url="{{ route('cms.admin.quizzes.create') }}"
     data-store-url="{{ route('cms.admin.quizzes.store') }}"
     data-show-url="{{ route('cms.admin.quizzes.show', ':id') }}"
     data-edit-url="{{ route('cms.admin.quizzes.edit', ':id') }}"
     data-update-url="{{ route('cms.admin.quizzes.update', ':id') }}"
     data-destroy-url="{{ route('cms.admin.quizzes.destroy', ':id') }}"
     data-trashed-url="{{ route('cms.admin.quizzes.trashed') }}"
     data-restore-url="{{ route('cms.admin.quizzes.restore', ':id') }}"
     data-force-delete-url="{{ route('cms.admin.quizzes.force-delete', ':id') }}"
     data-i18n='@json(__("quizzes.js"))'>

    @include('cms.partials.crud-header', [
        'icon' => 'fa-circle-question', 'title' => __('quizzes.title'), 'subtitle' => __('quizzes.subtitle'),
        'home' => __('quizzes.breadcrumb.home'), 'group' => __('quizzes.breadcrumb.group'),
        'addLabel' => __('quizzes.actions.add'), 'trashLabel' => __('quizzes.actions.trash'),
        'canAdd' => auth()->user()->can('create', \App\Models\Quiz::class),
    ])

    @include('cms.partials.stats-cards', ['stats' => $stats, 'cards' => [
        ['key' => 'total',   'icon' => 'fa-layer-group',  'tone' => 'primary', 'label' => __('quizzes.stats.total')],
        ['key' => 'ready',   'icon' => 'fa-circle-check', 'tone' => 'success', 'label' => __('quizzes.stats.ready')],
        ['key' => 'empty',   'icon' => 'fa-circle-minus', 'tone' => 'info',    'label' => __('quizzes.stats.empty')],
        ['key' => 'trashed', 'icon' => 'fa-trash-can',    'tone' => 'danger',  'label' => __('quizzes.stats.trashed')],
    ]])

    <section class="surface-card filters-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" data-filter="search" class="form-control" autocomplete="off"
                   placeholder="{{ __('quizzes.filters.search_placeholder') }}">
            <button type="button" class="search-clear" id="clearSearch" data-action="clear-search" hidden
                    aria-label="{{ __('quizzes.actions.clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <select data-filter="course_id" class="form-control filter-control">
            <option value="">{{ __('quizzes.filters.all_courses') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}">{{ $course->title_ar }} — {{ $course->title_en }}</option>
            @endforeach
        </select>

        <select data-filter="readiness" class="form-control filter-control">
            <option value="">{{ __('quizzes.filters.all_states') }}</option>
            <option value="ready">{{ __('quizzes.filters.ready_only') }}</option>
            <option value="empty">{{ __('quizzes.filters.empty_only') }}</option>
        </select>

        <button type="button" class="btn btn-light" data-action="reset-filters">
            <i class="fa-solid fa-rotate-left"></i> {{ __('quizzes.actions.reset') }}
        </button>
    </section>

    <section class="surface-card table-card" id="tableCard">
        <div id="tableContainer">
            @include('cms.quizzes.partials.table', ['quizzes' => $quizzes])
        </div>
        @include('cms.partials.table-skeleton')
    </section>

    @include('cms.quizzes.create')
    @include('cms.quizzes.edit')
    @include('cms.quizzes.show')
    @include('cms.quizzes.softdelete')
    @include('cms.quizzes.builder.modal')
</div>
@endsection