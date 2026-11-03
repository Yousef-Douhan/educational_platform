<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-bell"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('notifications.modal.show_title') }}</h2>
                <p>{{ __('notifications.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('notifications.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('notifications.modal.summary_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('notifications.table.when') }}</dt><dd dir="ltr" data-show="created_at_label">—</dd></div>
                    <div><dt>{{ __('notifications.fields.read_at') }}</dt><dd dir="ltr" data-show="read_at_label">—</dd></div>
                    <div><dt>{{ __('notifications.table.recipient') }}</dt><dd data-show="recipient_name">—</dd></div>
                    <div><dt>{{ __('notifications.fields.email') }}</dt><dd dir="ltr" data-show="recipient_email">—</dd></div>
                    <div><dt>{{ __('notifications.fields.kind') }}</dt><dd dir="ltr" data-show="kind">—</dd></div>
                    <div><dt>{{ __('notifications.table.state') }}</dt><dd data-show="state_label">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('notifications.table.notification') }}</dt><dd><strong data-show="title">—</strong><br><span data-show="body">—</span></dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-code"></i> {{ __('notifications.modal.params_section') }}</h4>
                <div data-params></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('notifications.ui.close') }}</button>
        </footer>
    </div>
</div>
