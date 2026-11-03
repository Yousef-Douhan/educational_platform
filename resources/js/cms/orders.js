import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor, Modal, setLoading, clearFieldErrors } from './crud-common';

const app = document.getElementById('ordersApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const URLS = {
        'mark-paid': app.dataset.markPaidUrl,
        cancel: app.dataset.cancelUrl,
        refund: app.dataset.refundUrl,
    };
    const TONE = { pending: 'warning', paid: 'success', failed: 'danger', cancelled: 'muted', refunded: 'info' };
    const MODAL_TONE = { 'mark-paid': 'primary', cancel: 'danger', refund: 'danger' };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const money = (v, currency = '') => (v === null || v === undefined ? '—'
        : `${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`.trim());

    function renderItems(container, items, currency) {
        container.replaceChildren();

        items.forEach((item) => {
            const row = el('div', 'order-item');
            const info = el('div', 'order-item__info');
            // textContent فقط: عناوين الدورات نصوص من المستخدمين ولا تُحقن كـ HTML
            info.append(el('div', 'order-item__title', item.title_ar || '—'));
            info.append(el('div', 'order-item__sub text-muted', item.title_en || ''));
            if (item.course_deleted) info.append(el('span', 'badge badge--muted', i18n.course_deleted));

            const price = el('div', 'order-item__price', money(item.price, currency));
            price.dir = 'ltr';
            row.append(info, price);
            container.append(row);
        });
    }

    /* ---------- نافذة الإجراءات ---------- */
    const actionModal = app.querySelector('#actionModal');
    const actionForm = app.querySelector('#actionForm');
    let current = null;

    function openAction(kind, id) {
        const t = i18n.actions[kind];
        current = { kind, id };

        actionForm.reset();
        clearFieldErrors(actionForm);

        actionModal.querySelector('[data-action-title]').textContent = t.title;
        actionModal.querySelector('[data-action-desc]').textContent = t.desc;
        actionModal.querySelector('[data-action-confirm]').textContent = t.confirm;
        actionModal.querySelector('[data-action-icon]').className = `crud-modal__icon tone-${MODAL_TONE[kind]}`;
        actionModal.querySelectorAll('[data-pay-only]').forEach((n) => { n.hidden = kind !== 'mark-paid'; });
        actionModal.querySelector('[data-note-required]').hidden = !t.note_required;
        actionForm.querySelector('[name="note"]').required = !!t.note_required;

        Modal.open(actionModal);
    }

    actionForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!current) return;

        const btn = actionForm.querySelector('[type=submit]');
        const fd = new FormData(actionForm);
        // حقول الدفع لا تُرسل لإجراء آخر
        if (current.kind !== 'mark-paid') { fd.delete('gateway'); fd.delete('transaction_reference'); }

        clearFieldErrors(actionForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(routeFor(URLS[current.kind], current.id), fd);
            Modal.close(actionModal);
            toast('success', data.message);
            await crud.loadRecords();
        } catch (err) {
            handleError(err, actionForm);
        } finally {
            setLoading(btn, false);
        }
    });

    const crud = createCrudModule(app, {
        onShow(modal, payload) {
            const o = payload.data;
            const dash = (v) => (v === null || v === undefined || v === '' ? null : v);

            setText(modal, 'order_number', o.order_number);
            setText(modal, 'name', o.user.name);
            setText(modal, 'email', o.user.email);
            setText(modal, 'created_at', o.created_at);
            setText(modal, 'paid_at', o.paid_at);
            setText(modal, 'refunded_at', o.refunded_at);
            setText(modal, 'gateway', o.gateway ? (i18n.gateways[o.gateway] || o.gateway) : null);
            setText(modal, 'transaction_reference', dash(o.transaction_reference));
            setText(modal, 'note', dash(o.note));
            setText(modal, 'subtotal', money(o.subtotal, o.currency));
            setText(modal, 'discount_amount', money(o.discount_amount, o.currency));
            setText(modal, 'total_amount', money(o.total_amount, o.currency));
            modal.querySelector('[data-show="coupon_code"]').textContent = o.coupon_code ? `(${o.coupon_code})` : '';
            modal.querySelector('[data-discount-row]').hidden = !(Number(o.discount_amount) > 0);
            modal.querySelector('[data-refunded-only]').hidden = o.status !== 'refunded';

            const badge = modal.querySelector('[data-show-status]');
            badge.textContent = i18n.statuses[o.status];
            badge.className = `badge badge--${TONE[o.status]}`;

            renderItems(modal.querySelector('[data-order-items]'), o.items, o.currency);
        },

        onAction(action, id) {
            if (URLS[action]) openAction(action, id);
        },
    });
}
