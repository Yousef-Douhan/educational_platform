@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('enrollments.modal.edit_title'), 'desc' => __('enrollments.modal.edit_desc'),
    'fields' => 'cms.enrollments.partials.form-fields',
    'closeLabel' => __('enrollments.actions.close'), 'cancelLabel' => __('enrollments.actions.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('enrollments.actions.update'),
])