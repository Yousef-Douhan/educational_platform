import { createCrudModule, setText } from './crud-module';

const app = document.getElementById('paymentsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const TONE = { pending: 'warning', success: 'success', failed: 'danger', refunded: 'info' };

    const money = (v, currency = '') => (v === null || v === undefined ? null
        : `${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`.trim());

    createCrudModule(app, {
        onShow(modal, payload) {
            const p = payload.data;

            setText(modal, 'order_number', p.order_number);
            setText(modal, 'name', p.user.name);
            setText(modal, 'email', p.user.email);
            setText(modal, 'gateway', p.gateway);
            setText(modal, 'payment_method', p.payment_method);
            setText(modal, 'amount', money(p.amount, p.currency));
            setText(modal, 'transaction_reference', p.transaction_reference);
            setText(modal, 'created_at', p.created_at);
            setText(modal, 'paid_at', p.paid_at);
            setText(modal, 'refunded_at', p.refunded_at);
            modal.querySelector('[data-refunded-only]').hidden = p.status !== 'refunded';

            const badge = modal.querySelector('[data-show-status]');
            badge.textContent = i18n.statuses[p.status];
            badge.className = `badge badge--${TONE[p.status]}`;

            // textContent فقط: استجابة البوابة نص خارجي ولا يُحقن كـ HTML أبدًا
            modal.querySelector('[data-gateway-response]').textContent = p.gateway_response || i18n.no_response;
        },
    });
}
