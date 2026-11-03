{{-- cms/live-sessions/edit.blade.php --}}
@include('cms.partials.form-modal', [
    'modalId' => 'editModal', 'formId' => 'editForm', 'prefix' => 'edit',
    'tone' => 'warning', 'icon' => 'fa-pen-to-square',
    'title' => __('live_sessions.modal.edit_title'), 'desc' => __('live_sessions.modal.edit_desc'),
    'fields' => 'cms.live-sessions.partials.form-fields', 'size' => 'lg',
    'closeLabel' => __('live_sessions.ui.close'), 'cancelLabel' => __('live_sessions.ui.cancel'),
    'submitIcon' => 'fa-check', 'submitLabel' => __('live_sessions.ui.update'),
])
