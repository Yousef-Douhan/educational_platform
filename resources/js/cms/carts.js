import { createCrudModule, setText } from './crud-module';
import { http, toast, confirmDialog, handleError, routeFor } from './crud-common';

const app = document.getElementById('cartsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const clearUrl = app.dataset.clearUrl;
    const TONE = { active: 'success', abandoned: 'danger', empty: 'muted' };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const money = (v) => (v === null || v === undefined ? '—'
        : Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

    function renderItems(container, items) {
        container.replaceChildren();

        if (!items.length) {
            container.append(el('p', 'text-muted', i18n.no_items));
            return;
        }

        items.forEach((item) => {
            const row = el('div', `cart-item${item.issue ? ' cart-item--invalid' : ''}`);
            const info = el('div', 'cart-item__info');
            // textContent فقط: عناوين الدورات نصوص من المستخدمين ولا تُحقن كـ HTML
            info.append(el('div', 'cart-item__title', item.title_ar || '—'));
            info.append(el('div', 'cart-item__sub text-muted', item.title_en || ''));
            if (item.issue) info.append(el('span', 'badge badge--danger', i18n.issues[item.issue] || item.issue));

            const price = el('div', 'cart-item__price', money(item.price));
            price.dir = 'ltr';
            row.append(info, price);
            container.append(row);
        });
    }

    const crud = createCrudModule(app, {
        onShow(modal, payload) {
            const c = payload.data;
            setText(modal, 'name', c.user.name);
            setText(modal, 'email', c.user.email);
            setText(modal, 'updated_at', c.updated_at);
            setText(modal, 'subtotal', money(c.subtotal));

            const badge = modal.querySelector('[data-show-state]');
            badge.textContent = i18n.states[c.state];
            badge.className = `badge badge--${TONE[c.state]}`;

            renderItems(modal.querySelector('[data-cart-items]'), c.items);
        },

        async onAction(action, id) {
            if (action !== 'clear') return;

            const ok = await confirmDialog({
                title: i18n.clear_title, text: i18n.clear_text,
                confirmText: i18n.clear_confirm, cancelText: i18n.cancel,
            });
            if (!ok) return;

            try {
                const { data } = await http.post(routeFor(clearUrl, id));
                toast('success', data.message);
                await crud.loadRecords();
            } catch (err) { handleError(err); }
        },
    });
}
