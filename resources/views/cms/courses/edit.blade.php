@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit', 'size' => 'lg',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('courses.modal.edit_title'), 'desc' => __('courses.modal.edit_desc'),
    'fields' => 'cms.courses.partials.form-fields',
    'closeLabel' => __('courses.actions.close'), 'cancelLabel' => __('courses.actions.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('courses.actions.update'),
])