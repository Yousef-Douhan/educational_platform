{{-- cms/coupons/create.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-circle-plus',
    'title' => __('coupons.modal.create_title'), 'desc' => __('coupons.modal.create_desc'),
    'fields' => 'cms.coupons.partials.form-fields', 'size' => 'lg',
    'closeLabel' => __('coupons.ui.close'), 'cancelLabel' => __('coupons.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('coupons.ui.save'),
])
