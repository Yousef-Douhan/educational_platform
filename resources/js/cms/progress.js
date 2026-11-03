import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor, confirmDialog } from './crud-common';

const app = document.getElementById('progressApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const RECALC_URL = app.dataset.recalculateUrl;

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const pct = (v) => `${Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })}%`;

    /* عناوين الدروس والأقسام نصوص من المستخدمين، فتُعرض بـ textContent فقط */
    function renderLessons(container, lessons) {
        container.replaceChildren();

        if (!lessons.length) {
            container.append(el('p', 'text-muted', i18n.no_lessons));
            return;
        }

        let lastSection = null;

        lessons.forEach((lesson) => {
            const section = lesson[i18n.section_key];

            if (section !== lastSection) {
                container.append(el('div', 'progress-section', section));
                lastSection = section;
            }

            const row = el('div', `progress-lesson${lesson.is_completed ? ' is-done' : ''}`);
            row.append(el('i', `fa-solid ${lesson.is_completed ? 'fa-circle-check' : 'fa-circle'} progress-lesson__icon`));

            const info = el('div', 'progress-lesson__info');
            info.append(el('div', 'progress-lesson__title', lesson[i18n.title_key]));
            info.append(el('div', 'text-muted small', i18n.types[lesson.type] || lesson.type));
            row.append(info);

            const when = el('div', 'progress-lesson__when', lesson.completed_at || '');
            when.dir = 'ltr';
            row.append(when);

            container.append(row);
        });
    }

    const crud = createCrudModule(app, {
        onShow(modal, payload) {
            const p = payload.data;
            const dash = (v) => (v === null || v === undefined || v === '' ? null : v);

            setText(modal, 'name', p.user.name);
            setText(modal, 'email', p.user.email);
            setText(modal, 'course', p.course[i18n.title_key]);
            setText(modal, 'enrollment', p.enrollment ? (i18n.enrollment_statuses[p.enrollment] || p.enrollment) : null);
            setText(modal, 'progress', `${pct(p.percentage)} (${p.completed}/${p.total})`);
            setText(modal, 'finished_at', dash(p.finished_at));
            setText(modal, 'updated_at', dash(p.updated_at));

            const badge = modal.querySelector('[data-show-state]');
            badge.textContent = p.is_finished ? i18n.finished : i18n.in_progress;
            badge.className = `badge badge--${p.is_finished ? 'success' : 'info'}`;

            renderLessons(modal.querySelector('[data-lessons]'), p.lessons);
        },

        async onAction(action, id) {
            if (action !== 'recalculate') return;

            const ok = await confirmDialog({
                title: i18n.recalc_title, text: i18n.recalc_text, confirmText: i18n.recalc_confirm,
                cancelText: i18n.cancel, icon: 'question', danger: false,
            });
            if (!ok) return;

            try {
                const { data } = await http.post(routeFor(RECALC_URL, id));
                toast('success', data.message);
                await crud.loadRecords();
            } catch (err) {
                handleError(err);
            }
        },
    });
}
