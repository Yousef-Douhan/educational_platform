@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create', 'size' => 'lg',
    'tone' => 'primary', 'icon' => 'fa-circle-plus',
    'title' => __('courses.modal.create_title'), 'desc' => __('courses.modal.create_desc'),
    'fields' => 'cms.courses.partials.form-fields',
    'closeLabel' => __('courses.actions.close'), 'cancelLabel' => __('courses.actions.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('courses.actions.save'),
])