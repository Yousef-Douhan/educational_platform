@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-shield-halved',
    'title' => __('roles.modal.create_title'), 'desc' => __('roles.modal.create_desc'),
    'fields' => 'cms.roles.partials.form-fields',
    'closeLabel' => __('roles.actions.close'), 'cancelLabel' => __('roles.actions.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('roles.actions.save'),
])