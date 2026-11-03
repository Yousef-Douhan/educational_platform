<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('carts.modal.show_title') }}</h2>
                <p>{{ __('carts.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('carts.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-user"></i> {{ __('carts.modal.student_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('carts.fields.student') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('carts.fields.email') }}</dt><dd dir="ltr" data-show="email">—</dd></div>
                    <div><dt>{{ __('carts.table.state') }}</dt><dd><span data-show-state class="badge badge--muted">—</span></dd></div>
                    <div><dt>{{ __('carts.table.updated_at') }}</dt><dd dir="ltr" data-show="updated_at">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-list"></i> {{ __('carts.modal.items_section') }}</h4>
                <div class="cart-items" data-cart-items></div>
                <div class="cart-total">
                    <span>{{ __('carts.modal.subtotal') }}</span>
                    <strong dir="ltr" data-show="subtotal">—</strong>
                </div>
                <small class="form-hint">{{ __('carts.hints.subtotal') }}</small>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('carts.ui.close') }}</button>
        </footer>
    </div>
</div>
