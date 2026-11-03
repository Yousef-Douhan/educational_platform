{{-- cms/coupons/edit.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('coupons.modal.edit_title'), 'desc' => __('coupons.modal.edit_desc'),
    'fields' => 'cms.coupons.partials.form-fields', 'size' => 'lg',
    'closeLabel' => __('coupons.ui.close'), 'cancelLabel' => __('coupons.ui.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('coupons.ui.update'),
])
