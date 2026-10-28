@php
    $builderUrls = [
        'tree'    => route('cms.admin.quizzes.questions.index', ':id'),
        'store'   => route('cms.admin.quizzes.questions.store', ':id'),
        'reorder' => route('cms.admin.quizzes.questions.reorder', ':id'),
        'trashed' => route('cms.admin.quizzes.questions.trashed', ':id'),
        'update'  => route('cms.admin.questions.update', ':id'),
        'destroy' => route('cms.admin.questions.destroy', ':id'),
        'restore' => route('cms.admin.questions.restore', ':id'),
        'force'   => route('cms.admin.questions.force-delete', ':id'),
    ];
@endphp

<div class="crud-modal" id="builderModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="builderModalTitle"
     data-urls='@json($builderUrls)' data-i18n='@json(__("quizzes.builder"))'>
    <div class="crud-modal__dialog crud-modal__dialog--xl">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-primary"><i class="fa-solid fa-list-check"></i></div>
            <div class="crud-modal__heading">
                <h2 id="builderModalTitle">{{ __('quizzes.ui.builder_title') }}</h2>
                <p data-qb-subtitle>{{ __('quizzes.ui.builder_subtitle') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('quizzes.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="qb-summary">
                @foreach (['questions', 'points', 'pass_mark'] as $stat)
                    <div class="qb-stat"><strong data-qb-stat="{{ $stat }}">0</strong><small>{{ __('quizzes.ui.stats.' . $stat) }}</small></div>
                @endforeach
            </div>

            <div class="alert-box alert-box--warning" data-qb-banner hidden>
                <i class="fa-solid fa-lock"></i><span>{{ __('quizzes.ui.lock_note') }}</span>
            </div>

            <div class="qb-toolbar">
                <button type="button" class="btn btn-primary btn-sm" data-qb="add-question">
                    <i class="fa-solid fa-plus"></i> {{ __('quizzes.ui.add_question') }}
                </button>
                <button type="button" class="btn btn-light btn-sm" data-qb="toggle-trash">
                    <i class="fa-solid fa-trash-can-arrow-up"></i> <span data-qb-trash-label>{{ __('quizzes.ui.trash') }}</span>
                </button>
            </div>

            <div data-qb-list></div>
            <div data-qb-trash hidden></div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('quizzes.ui.close') }}</button>
        </footer>
    </div>
</div>

@include('cms.partials.form-modal', [
    'modalId' => 'questionModal', 'formId' => 'questionForm', 'prefix' => 'question',
    'modalClass' => 'crud-modal--top', 'size' => 'lg',
    'tone' => 'info', 'icon' => 'fa-circle-question',
    'title' => __('quizzes.builder.question_create_title'), 'desc' => __('quizzes.ui.question_desc'),
    'fields' => 'cms.quizzes.builder.question-fields',
    'closeLabel' => __('quizzes.ui.close'), 'cancelLabel' => __('quizzes.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('quizzes.ui.save'),
])