{{-- cms/certificates/revoke.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'revokeModal', 'formId' => 'revokeForm', 'prefix' => 'revoke', 'modalClass' => 'crud-modal--top',
    'tone' => 'danger', 'icon' => 'fa-ban',
    'title' => __('certificates.modal.revoke_title'), 'desc' => __('certificates.modal.revoke_desc'),
    'fields' => 'cms.certificates.partials.revoke-fields',
    'closeLabel' => __('certificates.ui.close'), 'cancelLabel' => __('certificates.ui.cancel'),
    'submitIcon' => 'fa-ban', 'submitLabel' => __('certificates.actions.revoke'),
])