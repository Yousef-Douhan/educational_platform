import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor } from './crud-common';

const adminApp = document.getElementById('notificationsApp');
const mineApp = document.getElementById('myNotificationsApp');

const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

/* المعاملات نصوص قادمة من بيانات المستخدمين، فتُعرض بـ textContent فقط */
function renderParams(container, params) {
    container.replaceChildren();
    const keys = Object.keys(params || {});
    if (!keys.length) {
        container.append(el('p', 'text-muted', '—'));
        return;
    }
    const table = el('table', 'changes-table');
    const body = el('tbody');
    keys.forEach((key) => {
        const row = el('tr');
        row.append(el('th', '', key), el('td', '', String(params[key] ?? '—')));
        body.append(row);
    });
    table.append(body);
    container.append(table);
}

if (adminApp) {
    const i18n = JSON.parse(adminApp.dataset.i18n);

    createCrudModule(adminApp, {
        onShow(modal, payload) {
            const d = payload.data;
            setText(modal, 'created_at_label', d.created_at_label);
            setText(modal, 'read_at_label', d.read_at_label);
            setText(modal, 'recipient_name', d.recipient_name);
            setText(modal, 'recipient_email', d.recipient_email);
            setText(modal, 'kind', d.kind);
            setText(modal, 'state_label', d.is_read ? i18n.read : i18n.unread);
            setText(modal, 'title', d.title);
            setText(modal, 'body', d.body);
            renderParams(modal.querySelector('[data-params]'), d.params);
        },
    });
}

if (mineApp) {
    const urls = { read: mineApp.dataset.readUrl, readAll: mineApp.dataset.readAllUrl };
    let module;

    /* شارة الجرس في الترويسة تتبع عدّاد «غير مقروءة» بعد كل عملية (قراءة أو حذف أو تنقل) */
    const syncBadge = () => {
        const n = Number(mineApp.querySelector('[data-stat="unread"]')?.textContent ?? 0);
        document.querySelectorAll('[data-notif-badge]').forEach((badge) => {
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.hidden = n < 1;
        });
    };

    const unreadStat = mineApp.querySelector('[data-stat="unread"]');
    if (unreadStat) new MutationObserver(syncBadge).observe(unreadStat, { childList: true, characterData: true, subtree: true });

    const post = async (url) => {
        try {
            const { data } = await http.post(url);
            toast('success', data.message);
            await module.loadRecords();
        } catch (err) {
            handleError(err);
        }
    };

    module = createCrudModule(mineApp, {
        onAction(action, id) {
            if (action === 'read') return post(routeFor(urls.read, id));
            if (action === 'read-all') return post(urls.readAll);
            return undefined;
        },
    });
}
