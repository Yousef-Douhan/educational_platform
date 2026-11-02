{{-- cms/certificates/create.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-award',
    'title' => __('certificates.modal.issue_title'), 'desc' => __('certificates.modal.issue_desc'),
    'fields' => 'cms.certificates.partials.form-fields',
    'closeLabel' => __('certificates.ui.close'), 'cancelLabel' => __('certificates.ui.cancel'),
    'submitIcon' => 'fa-stamp', 'submitLabel' => __('certificates.actions.issue'),
])