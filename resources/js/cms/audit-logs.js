import { createCrudModule, setText } from './crud-module';

const app = document.getElementById('auditLogsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const show = (v) => {
        if (v === null || v === undefined || v === '') return '—';
        if (typeof v === 'boolean') return v ? 'true' : 'false';
        return String(v);
    };

    /* القيم نصوص من المستخدمين، فتُعرض بـ textContent فقط */
    function renderChanges(container, oldV, newV) {
        container.replaceChildren();
        const keys = [...new Set([...Object.keys(oldV || {}), ...Object.keys(newV || {})])];

        if (!keys.length) {
            container.append(el('p', 'text-muted', i18n.no_changes));
            return;
        }

        const table = el('table', 'changes-table');
        const head = el('tr');
        [i18n.field, i18n.old_value, i18n.new_value].forEach((t) => head.append(el('th', '', t)));
        const thead = el('thead');
        thead.append(head);
        table.append(thead);

        const body = el('tbody');
        keys.forEach((key) => {
            const row = el('tr');
            const o = oldV?.[key];
            const n = newV?.[key];
            const cellOld = el('td', 'is-old', show(o));
            const cellNew = el('td', 'is-new', show(n));
            [cellOld, cellNew].forEach((c, i) => {
                if ((i === 0 ? o : n) === '[redacted]') {
                    c.classList.add('is-redacted');
                    c.textContent = i18n.redacted;
                }
            });
            if (!(key in (oldV || {}))) cellOld.className = '';
            if (!(key in (newV || {}))) cellNew.className = '';
            row.append(el('th', '', key), cellOld, cellNew);
            body.append(row);
        });
        table.append(body);
        container.append(table);
    }

    createCrudModule(app, {
        onShow(modal, payload) {
            const d = payload.data;
            const type = i18n.types[d.type] ?? d.type ?? '—';
            const event = i18n.events[d.event] ?? d.event;

            setText(modal, 'created_at_label', d.created_at_label);
            setText(modal, 'action_label', `${type} — ${event}`);
            setText(modal, 'user_name', d.user_name ?? i18n.system);
            setText(modal, 'user_email', d.user_email);
            setText(modal, 'subject_label', d.subject_label);
            setText(modal, 'subject_id', d.subject_id);
            setText(modal, 'ip_address', d.ip_address);
            setText(modal, 'user_agent', d.user_agent);
            setText(modal, 'description', d.description);

            renderChanges(modal.querySelector('[data-changes]'), d.old_values, d.new_values);
        },
    });
}
