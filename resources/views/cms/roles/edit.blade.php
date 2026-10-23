@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('roles.modal.edit_title'), 'desc' => __('roles.modal.edit_desc'),
    'fields' => 'cms.roles.partials.form-fields',
    'closeLabel' => __('roles.actions.close'), 'cancelLabel' => __('roles.actions.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('roles.actions.update'),
])