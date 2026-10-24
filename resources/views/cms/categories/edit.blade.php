@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('categories.modal.edit_title'), 'desc' => __('categories.modal.edit_desc'),
    'fields' => 'cms.categories.partials.form-fields',
    'closeLabel' => __('categories.actions.close'), 'cancelLabel' => __('categories.actions.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('categories.actions.update'),
])