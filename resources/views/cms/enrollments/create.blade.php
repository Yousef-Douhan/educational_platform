@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-user-plus',
    'title' => __('enrollments.modal.create_title'), 'desc' => __('enrollments.modal.create_desc'),
    'fields' => 'cms.enrollments.partials.form-fields',
    'closeLabel' => __('enrollments.actions.close'), 'cancelLabel' => __('enrollments.actions.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('enrollments.actions.save'),
])