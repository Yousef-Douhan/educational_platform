{{-- cms/live-sessions/create.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'createModal', 'formId' => 'createForm', 'prefix' => 'create',
    'tone' => 'primary', 'icon' => 'fa-circle-plus',
    'title' => __('live_sessions.modal.create_title'), 'desc' => __('live_sessions.modal.create_desc'),
    'fields' => 'cms.live-sessions.partials.form-fields', 'size' => 'lg',
    'closeLabel' => __('live_sessions.ui.close'), 'cancelLabel' => __('live_sessions.ui.cancel'),
    'submitIcon' => 'fa-floppy-disk', 'submitLabel' => __('live_sessions.ui.save'),
])
