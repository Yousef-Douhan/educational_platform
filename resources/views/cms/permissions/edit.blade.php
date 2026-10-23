@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('permissions.modal.edit_title'), 'desc' => __('permissions.modal.edit_desc'),
    'fields' => 'cms.permissions.partials.form-fields',
    'closeLabel' => __('permissions.actions.close'), 'cancelLabel' => __('permissions.actions.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('permissions.actions.update'),
])