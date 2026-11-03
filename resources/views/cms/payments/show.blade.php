<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-credit-card"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('payments.modal.show_title') }}</h2>
                <p>{{ __('payments.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('payments.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('payments.modal.payment_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('payments.table.order') }}</dt><dd dir="ltr" data-show="order_number">—</dd></div>
                    <div><dt>{{ __('payments.table.status') }}</dt><dd><span data-show-status class="badge badge--muted">—</span></dd></div>
                    <div><dt>{{ __('payments.fields.student') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('payments.fields.email') }}</dt><dd dir="ltr" data-show="email">—</dd></div>
                    <div><dt>{{ __('payments.fields.gateway') }}</dt><dd dir="ltr" data-show="gateway">—</dd></div>
                    <div><dt>{{ __('payments.fields.payment_method') }}</dt><dd dir="ltr" data-show="payment_method">—</dd></div>
                    <div><dt>{{ __('payments.table.amount') }}</dt><dd dir="ltr" data-show="amount">—</dd></div>
                    <div><dt>{{ __('payments.fields.transaction_reference') }}</dt><dd dir="ltr" data-show="transaction_reference">—</dd></div>
                    <div><dt>{{ __('payments.table.created_at') }}</dt><dd dir="ltr" data-show="created_at">—</dd></div>
                    <div><dt>{{ __('payments.fields.paid_at') }}</dt><dd dir="ltr" data-show="paid_at">—</dd></div>
                    <div data-refunded-only><dt>{{ __('payments.fields.refunded_at') }}</dt><dd dir="ltr" data-show="refunded_at">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-code"></i> {{ __('payments.modal.response_section') }}</h4>
                <pre class="gateway-response" dir="ltr" data-gateway-response>—</pre>
                <small class="form-hint">{{ __('payments.hints.response') }}</small>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('payments.ui.close') }}</button>
        </footer>
    </div>
</div>
