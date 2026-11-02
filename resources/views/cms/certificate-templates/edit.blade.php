{{-- cms/certificate-templates/edit.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('certificate-templates.modal.edit_title'), 'desc' => __('certificate-templates.modal.edit_desc'),
    'fields' => 'cms.certificate-templates.partials.form-fields',
    'closeLabel' => __('certificate-templates.ui.close'), 'cancelLabel' => __('certificate-templates.ui.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('certificate-templates.ui.update'),
])