import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor, Modal, setLoading, clearFieldErrors } from './crud-common';

const app = document.getElementById('liveSessionsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const CANCEL_URL = app.dataset.cancelUrl;
    const TONE = { upcoming: 'info', live: 'success', ended: 'muted', cancelled: 'danger' };
    const isAr = document.documentElement.lang === 'ar';
    const pick = (ar, en) => (isAr ? ar : en);

    /* ---------- نافذة الإلغاء ---------- */
    const cancelModal = app.querySelector('#cancelModal');
    const cancelForm = app.querySelector('#cancelForm');
    let currentId = null;

    cancelForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentId) return;

        const btn = cancelForm.querySelector('[type=submit]');
        clearFieldErrors(cancelForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(routeFor(CANCEL_URL, currentId), new FormData(cancelForm));
            Modal.close(cancelModal);
            toast('success', data.message);
            await crud.loadRecords();
        } catch (err) {
            handleError(err, cancelForm);
        } finally {
            setLoading(btn, false);
        }
    });

    /** الرابط يصير <a> فقط إذا كان https، وبـ textContent دون innerHTML */
    function renderLink(holder, url) {
        holder.replaceChildren();
        if (typeof url === 'string' && url.startsWith('https://')) {
            const a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'meeting-link';
            a.textContent = url;
            holder.append(a);
        } else {
            holder.textContent = url || '—';
        }
    }

    const crud = createCrudModule(app, {
        onCreate(form, data) {
            const select = form.querySelector('[data-course-select]');
            select.replaceChildren(
                new Option(i18n.select_course, ''),
                ...data.options.courses.map((c) => new Option(pick(c.title_ar, c.title_en), c.id)),
            );
            form.elements.duration_minutes.value = 60;
        },

        onEdit(form, payload) {
            const s = payload.data;
            form.querySelector('[data-edit-course]').textContent = pick(s.course_title_ar, s.course_title_en) || '—';
            form.elements.title_ar.value = s.title_ar;
            form.elements.title_en.value = s.title_en;
            form.elements.meeting_url.value = s.meeting_url;
            form.elements.scheduled_at.value = s.scheduled_at_input ?? '';
            form.elements.duration_minutes.value = s.duration_minutes;
        },

        onShow(modal, payload) {
            const s = payload.data;
            setText(modal, 'title', pick(s.title_ar, s.title_en));
            setText(modal, 'course', pick(s.course_title_ar, s.course_title_en));
            setText(modal, 'title_ar', s.title_ar);
            setText(modal, 'title_en', s.title_en);
            setText(modal, 'duration', `${s.duration_minutes} ${i18n.minutes}`);
            setText(modal, 'scheduled_at_label', s.scheduled_at_label);
            setText(modal, 'ends_at_label', s.ends_at_label);
            setText(modal, 'created_at_label', s.created_at_label);
            setText(modal, 'updated_at_label', s.updated_at_label);
            renderLink(modal.querySelector('[data-show-link]'), s.meeting_url);

            const badge = modal.querySelector('[data-show-phase]');
            badge.textContent = i18n.phases[s.phase];
            badge.className = `badge badge--${TONE[s.phase]}`;

            modal.querySelector('[data-cancelled-only]').hidden = s.phase !== 'cancelled';
            setText(modal, 'cancelled_at_label', s.cancelled_at_label);
            setText(modal, 'cancel_reason', s.cancel_reason);
        },

        onAction(action, id) {
            if (action !== 'cancel') return;

            currentId = id;
            cancelForm.reset();
            clearFieldErrors(cancelForm);
            Modal.open(cancelModal);
        },
    });
}
