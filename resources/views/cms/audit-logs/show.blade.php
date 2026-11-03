<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('audit_logs.modal.show_title') }}</h2>
                <p>{{ __('audit_logs.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('audit_logs.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('audit_logs.modal.summary_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('audit_logs.table.when') }}</dt><dd dir="ltr" data-show="created_at_label">—</dd></div>
                    <div><dt>{{ __('audit_logs.table.action') }}</dt><dd data-show="action_label">—</dd></div>
                    <div><dt>{{ __('audit_logs.table.user') }}</dt><dd data-show="user_name">—</dd></div>
                    <div><dt>{{ __('audit_logs.fields.email') }}</dt><dd dir="ltr" data-show="user_email">—</dd></div>
                    <div><dt>{{ __('audit_logs.table.subject') }}</dt><dd data-show="subject_label">—</dd></div>
                    <div><dt>{{ __('audit_logs.fields.subject_id') }}</dt><dd dir="ltr" data-show="subject_id">—</dd></div>
                    <div><dt>{{ __('audit_logs.table.ip') }}</dt><dd dir="ltr" data-show="ip_address">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('audit_logs.fields.user_agent') }}</dt><dd dir="ltr" data-show="user_agent">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('audit_logs.fields.description') }}</dt><dd data-show="description">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-code-compare"></i> {{ __('audit_logs.modal.changes_section') }}</h4>
                <div data-changes></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('audit_logs.ui.close') }}</button>
        </footer>
    </div>
</div>
