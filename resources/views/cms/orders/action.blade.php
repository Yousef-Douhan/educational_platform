{{-- نافذة واحدة لإجراءات الطلب الثلاثة (تأكيد الدفع / الإلغاء / الاسترداد)؛ الجافاسكربت يضبط نصوصها وحقولها --}}
<div class="crud-modal" id="actionModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="actionModalTitle">
    <form class="crud-modal__dialog" id="actionForm" novalidate>
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-primary" data-action-icon><i class="fa-solid fa-money-check-dollar"></i></div>
            <div class="crud-modal__heading">
                <h2 id="actionModalTitle" data-action-title>—</h2>
                <p data-action-desc>—</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('orders.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="form-grid">
                <div class="form-group form-group--full" data-pay-only>
                    <label for="action_gateway" class="form-label">{{ __('orders.fields.gateway') }} <span class="required">*</span></label>
                    <select id="action_gateway" name="gateway" class="form-control">
                        @foreach (\App\Models\Order::MANUAL_GATEWAYS as $g)
                            <option value="{{ $g }}">{{ __('orders.gateways.' . $g) }}</option>
                        @endforeach
                    </select>
                    <div class="field-error" data-error-for="gateway"></div>
                </div>

                <div class="form-group form-group--full" data-pay-only>
                    <label for="action_reference" class="form-label">{{ __('orders.fields.transaction_reference') }}</label>
                    <input type="text" id="action_reference" name="transaction_reference" class="form-control" maxlength="120"
                           dir="ltr" autocomplete="off" placeholder="{{ __('orders.placeholders.reference') }}">
                    <small class="form-hint">{{ __('orders.hints.reference') }}</small>
                    <div class="field-error" data-error-for="transaction_reference"></div>
                </div>

                <div class="form-group form-group--full">
                    <label for="action_note" class="form-label">
                        {{ __('orders.fields.note') }} <span class="required" data-note-required hidden>*</span>
                    </label>
                    <textarea id="action_note" name="note" class="form-control" rows="3" maxlength="500"></textarea>
                    <div class="field-error" data-error-for="note"></div>
                </div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('orders.ui.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check btn-icon"></i><span class="spinner"></span>
                <span data-action-confirm>{{ __('orders.ui.confirm') }}</span>
            </button>
        </footer>
    </form>
</div>
