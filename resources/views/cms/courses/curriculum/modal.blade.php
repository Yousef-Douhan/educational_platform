@php
    $curUrls = [
        'tree'            => route('cms.admin.courses.curriculum', ':id'),
        'sectionStore'    => route('cms.admin.courses.sections.store', ':id'),
        'sectionsReorder' => route('cms.admin.courses.sections.reorder', ':id'),
        'sectionUpdate'   => route('cms.admin.sections.update', ':id'),
        'sectionDestroy'  => route('cms.admin.sections.destroy', ':id'),
        'lessonStore'     => route('cms.admin.sections.lessons.store', ':id'),
        'lessonsReorder'  => route('cms.admin.sections.lessons.reorder', ':id'),
        'lessonShow'      => route('cms.admin.lessons.show', ':id'),
        'lessonUpdate'    => route('cms.admin.lessons.update', ':id'),
        'lessonDestroy'   => route('cms.admin.lessons.destroy', ':id'),
        'trashed'         => route('cms.admin.courses.lessons.trashed', ':id'),
        'lessonRestore'   => route('cms.admin.lessons.restore', ':id'),
        'lessonForce'     => route('cms.admin.lessons.force-delete', ':id'),
        'attStore'        => route('cms.admin.lessons.attachments.store', ':id'),
        'attDestroy'      => route('cms.admin.attachments.destroy', ':id'),
    ];
@endphp

<div class="crud-modal" id="curriculumModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="curriculumModalTitle"
     data-urls='@json($curUrls)' data-i18n='@json(__("curriculum.js"))'>
    <div class="crud-modal__dialog crud-modal__dialog--xl">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-primary"><i class="fa-solid fa-layer-group"></i></div>
            <div class="crud-modal__heading">
                <h2 id="curriculumModalTitle">{{ __('curriculum.ui.title') }}</h2>
                <p data-cur-course>{{ __('curriculum.ui.subtitle') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('curriculum.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="cur-stats">
                @foreach (['sections', 'lessons', 'published', 'minutes'] as $stat)
                    <div class="cur-stat">
                        <strong data-cur-stat="{{ $stat }}">0</strong>
                        <small>{{ __('curriculum.ui.stats.' . $stat) }}</small>
                    </div>
                @endforeach
            </div>

            <div class="cur-toolbar">
                <button type="button" class="btn btn-primary btn-sm" data-cur="add-section">
                    <i class="fa-solid fa-plus"></i> {{ __('curriculum.ui.add_section') }}
                </button>
                <button type="button" class="btn btn-light btn-sm" data-cur="toggle-trash">
                    <i class="fa-solid fa-trash-can-arrow-up"></i> <span data-cur-trash-label>{{ __('curriculum.ui.trash') }}</span>
                </button>
            </div>

            <div data-cur-tree></div>
            <div data-cur-trash hidden></div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('curriculum.ui.close') }}</button>
        </footer>
    </div>
</div>

@include('cms.partials.form-modal', [
    'modalId' => 'sectionModal', 'formId' => 'sectionForm', 'prefix' => 'section', 'modalClass' => 'crud-modal--top',
    'tone' => 'primary', 'icon' => 'fa-folder-plus',
    'title' => __('curriculum.js.section_create_title'), 'desc' => __('curriculum.ui.section_desc'),
    'fields' => 'cms.courses.curriculum.section-fields',
    'closeLabel' => __('curriculum.ui.close'), 'cancelLabel' => __('curriculum.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('curriculum.ui.save'),
])

@include('cms.partials.form-modal', [
    'modalId' => 'lessonModal', 'formId' => 'lessonForm', 'prefix' => 'lesson', 'modalClass' => 'crud-modal--top', 'size' => 'lg',
    'tone' => 'info', 'icon' => 'fa-chalkboard',
    'title' => __('curriculum.js.lesson_create_title'), 'desc' => __('curriculum.ui.lesson_desc'),
    'fields' => 'cms.courses.curriculum.lesson-fields',
    'closeLabel' => __('curriculum.ui.close'), 'cancelLabel' => __('curriculum.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('curriculum.ui.save'),
])