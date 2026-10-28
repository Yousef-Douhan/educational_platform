{{-- create.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-circle-plus',
    'title' => __('quizzes.modal.create_title'), 'desc' => __('quizzes.modal.create_desc'),
    'fields' => 'cms.quizzes.partials.form-fields',
    'closeLabel' => __('quizzes.ui.close'), 'cancelLabel' => __('quizzes.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('quizzes.ui.save'),
])