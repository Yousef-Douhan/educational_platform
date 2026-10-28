{{-- edit.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('quizzes.modal.edit_title'), 'desc' => __('quizzes.modal.edit_desc'),
    'fields' => 'cms.quizzes.partials.form-fields',
    'closeLabel' => __('quizzes.ui.close'), 'cancelLabel' => __('quizzes.ui.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('quizzes.actions.update'),
])