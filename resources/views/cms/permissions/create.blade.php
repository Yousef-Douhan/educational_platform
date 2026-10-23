@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-key',
    'title' => __('permissions.modal.create_title'), 'desc' => __('permissions.modal.create_desc'),
    'fields' => 'cms.permissions.partials.form-fields',
    'closeLabel' => __('permissions.actions.close'), 'cancelLabel' => __('permissions.actions.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('permissions.actions.save'),
])