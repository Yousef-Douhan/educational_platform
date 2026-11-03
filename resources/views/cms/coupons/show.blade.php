<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-ticket"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('coupons.modal.show_title') }}</h2>
                <p>{{ __('coupons.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('coupons.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="coupon-hero">
                <span class="coupon-code coupon-code--lg" dir="ltr" data-show="code">—</span>
                <span class="badge badge--muted" data-show-status>—</span>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-percent"></i> {{ __('coupons.modal.discount_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('coupons.fields.discount_type') }}</dt><dd data-show="type_label">—</dd></div>
                    <div><dt>{{ __('coupons.fields.discount_value') }}</dt><dd dir="ltr" data-show="value_label">—</dd></div>
                    <div><dt>{{ __('coupons.fields.max_discount') }}</dt><dd dir="ltr" data-show="max_discount">—</dd></div>
                    <div><dt>{{ __('coupons.fields.min_order_amount') }}</dt><dd dir="ltr" data-show="min_order_amount">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-gauge"></i> {{ __('coupons.modal.usage_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('coupons.table.usage') }}</dt><dd dir="ltr" data-show="usage_label">—</dd></div>
                    <div><dt>{{ __('coupons.fields.per_user_limit') }}</dt><dd data-show="per_user_limit">—</dd></div>
                    <div><dt>{{ __('coupons.fields.starts_at') }}</dt><dd dir="ltr" data-show="starts_at_label">—</dd></div>
                    <div><dt>{{ __('coupons.fields.expires_at') }}</dt><dd dir="ltr" data-show="expires_at_label">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('coupons.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('coupons.table.created_at') }}</dt><dd dir="ltr" data-show="created_at_label">—</dd></div>
                    <div><dt>{{ __('coupons.table.updated_at') }}</dt><dd dir="ltr" data-show="updated_at_label">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('coupons.ui.close') }}</button>
        </footer>
    </div>
</div>
