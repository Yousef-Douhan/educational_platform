{{-- cms/certificate-templates/create.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-circle-plus',
    'title' => __('certificate-templates.modal.create_title'), 'desc' => __('certificate-templates.modal.create_desc'),
    'fields' => 'cms.certificate-templates.partials.form-fields',
    'closeLabel' => __('certificate-templates.ui.close'), 'cancelLabel' => __('certificate-templates.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('certificate-templates.ui.save'),
])