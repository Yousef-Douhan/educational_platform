<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-receipt"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('orders.modal.show_title') }}</h2>
                <p>{{ __('orders.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('orders.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('orders.modal.order_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('orders.table.number') }}</dt><dd dir="ltr" data-show="order_number">—</dd></div>
                    <div><dt>{{ __('orders.table.status') }}</dt><dd><span data-show-status class="badge badge--muted">—</span></dd></div>
                    <div><dt>{{ __('orders.fields.student') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('orders.fields.email') }}</dt><dd dir="ltr" data-show="email">—</dd></div>
                    <div><dt>{{ __('orders.table.created_at') }}</dt><dd dir="ltr" data-show="created_at">—</dd></div>
                    <div><dt>{{ __('orders.fields.paid_at') }}</dt><dd dir="ltr" data-show="paid_at">—</dd></div>
                    <div><dt>{{ __('orders.fields.gateway') }}</dt><dd data-show="gateway">—</dd></div>
                    <div><dt>{{ __('orders.fields.transaction_reference') }}</dt><dd dir="ltr" data-show="transaction_reference">—</dd></div>
                    <div data-refunded-only><dt>{{ __('orders.fields.refunded_at') }}</dt><dd dir="ltr" data-show="refunded_at">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('orders.fields.note') }}</dt><dd data-show="note">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-list"></i> {{ __('orders.modal.items_section') }}</h4>
                <div class="order-items" data-order-items></div>

                <div class="order-totals">
                    <div><span>{{ __('orders.fields.subtotal') }}</span><strong dir="ltr" data-show="subtotal">—</strong></div>
                    <div data-discount-row><span>{{ __('orders.fields.discount') }} <small dir="ltr" data-show="coupon_code"></small></span><strong dir="ltr" data-show="discount_amount">—</strong></div>
                    <div class="order-totals__final"><span>{{ __('orders.fields.total') }}</span><strong dir="ltr" data-show="total_amount">—</strong></div>
                </div>
                <small class="form-hint">{{ __('orders.hints.frozen') }}</small>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-credit-card"></i> {{ __('orders.modal.payments_section') }}</h4>
                <div class="order-items" data-order-payments></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('orders.ui.close') }}</button>
        </footer>
    </div>
</div>
