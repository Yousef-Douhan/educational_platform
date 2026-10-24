@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-folder-plus',
    'title' => __('categories.modal.create_title'), 'desc' => __('categories.modal.create_desc'),
    'fields' => 'cms.categories.partials.form-fields',
    'closeLabel' => __('categories.actions.close'), 'cancelLabel' => __('categories.actions.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('categories.actions.save'),
])