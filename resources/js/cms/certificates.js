import { createCrudModule, setText } from './crud-module';
import {
    http, toast, confirmDialog, Modal, setLoading, clearFieldErrors, handleError, debounce, routeFor,
} from './crud-common';
import { el } from './dom';

const app = document.getElementById('certificatesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const urls = {
        eligible: app.dataset.eligibleUrl, revoke: app.dataset.revokeUrl,
        reinstate: app.dataset.reinstateUrl, download: app.dataset.downloadUrl,
    };

    const revokeModal = app.querySelector('#revokeModal');
    const revokeForm = app.querySelector('#revokeForm');
    const initial = (name) => (name || '?').trim().charAt(0).toUpperCase();

    let revokeId = null;
    let picker = null;
    let api = null;

    /* ---------- اختيار الطالب المستحق ---------- */
    function initPicker(form) {
        const courseSel = form.querySelector('[data-course-select]');
        const search = form.querySelector('[data-student-search]');
        const searchWrap = form.querySelector('[data-student-search-wrap]');
        const hidden = form.querySelector('[data-student-id]');
        const picked = form.querySelector('[data-picked-student]');
        const results = form.querySelector('[data-student-results]');
        let seq = 0;

        function clearPick() {
            hidden.value = '';
            picked.hidden = true;
            searchWrap.hidden = false;
        }

        function reset() {
            clearPick();
            search.value = '';
            search.disabled = !courseSel.value;
            results.hidden = true;
            results.replaceChildren();
        }

        function choose(user) {
            hidden.value = user.id;
            picked.querySelector('[data-picked-initial]').textContent = initial(user.name);
            picked.querySelector('[data-picked-name]').textContent = user.name;
            picked.querySelector('[data-picked-email]').textContent = user.email;
            picked.hidden = false;
            searchWrap.hidden = true;
            results.hidden = true;
        }

        function render(users) {
            results.replaceChildren();
            if (!users.length) results.append(el('div', 'user-results__empty', i18n.no_students));
            users.forEach((u) => {
                const btn = el('button', 'user-result');
                btn.type = 'button';
                const info = el('div');
                info.append(el('strong', '', u.name), el('small', '', u.email));
                btn.append(el('span', 'avatar avatar--initial', initial(u.name)), info);
                btn.addEventListener('click', () => choose(u));
                results.append(btn);
            });
            results.hidden = false;
        }

        async function load() {
            if (!courseSel.value) return;
            const id = ++seq;
            try {
                const { data } = await http.get(urls.eligible, { params: { course_id: courseSel.value, search: search.value.trim() } });
                if (id === seq) render(data.data);
            } catch (err) { handleError(err); }
        }

        const debounced = debounce(load, 350);
        search.addEventListener('input', debounced);
        search.addEventListener('focus', load);
        courseSel.addEventListener('change', () => { reset(); load(); });
        form.querySelector('[data-student-clear]').addEventListener('click', () => { clearPick(); search.focus(); });
        document.addEventListener('click', (e) => {
            if (!form.querySelector('[data-student-picker]').contains(e.target)) results.hidden = true;
        });

        return { reset };
    }

    /* ---------- الإلغاء وإعادة التفعيل ---------- */
    function openRevoke(id) {
        revokeId = id;
        revokeForm.reset();
        clearFieldErrors(revokeForm);
        Modal.open(revokeModal);
    }

    async function submitRevoke() {
        const btn = revokeForm.querySelector('[type=submit]');
        clearFieldErrors(revokeForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(routeFor(urls.revoke, revokeId), { reason: revokeForm.elements.reason.value });
            Modal.close(revokeModal);
            toast('success', data.message);
            await api.loadRecords();
        } catch (err) { handleError(err, revokeForm); }
        finally { setLoading(btn, false); }
    }

    async function reinstate(id) {
        const ok = await confirmDialog({
            title: i18n.reinstate_title, text: i18n.reinstate_text,
            confirmText: i18n.reinstate_confirm, cancelText: i18n.cancel, icon: 'question', danger: false,
        });
        if (!ok) return;
        try {
            const { data } = await http.post(routeFor(urls.reinstate, id));
            toast('success', data.message);
            await api.loadRecords();
        } catch (err) { handleError(err); }
    }

    api = createCrudModule(app, {
        init() {
            picker = initPicker(app.querySelector('#createForm'));
            revokeForm.addEventListener('submit', (e) => { e.preventDefault(); submitRevoke(); });

            app.querySelector('[data-copy-link]').addEventListener('click', async () => {
                const text = app.querySelector('#showModal [data-show="verification_url"]').textContent;
                try {
                    await navigator.clipboard.writeText(text);
                    toast('success', i18n.copied);
                } catch { toast('error', i18n.copy_failed); }
            });
        },

        onCreate(form, data) {
            const { courses, templates } = data.options;
            form.querySelector('[data-no-courses]').hidden = courses.length > 0;

            form.querySelector('[data-course-select]').replaceChildren(
                new Option(i18n.select_course, ''),
                ...courses.map((c) => new Option(`${c.title_ar} — ${c.title_en}`, c.id)),
            );
            form.querySelector('[data-template-select]').replaceChildren(
                new Option(i18n.default_design, ''),
                ...templates.map((t) => new Option(t.is_default ? `${t.name} ★` : t.name, t.id)),
            );
            picker.reset();
        },

        onShow(modal, payload) {
            const c = payload.data;
            setText(modal, 'student_name', c.student.name);
            setText(modal, 'student_email', c.student.email);
            setText(modal, 'code', c.certificate_code);
            setText(modal, 'course', `${c.course.title_ar} — ${c.course.title_en}`);
            setText(modal, 'template', c.template?.name ?? i18n.default_design);
            setText(modal, 'issued_by', c.issued_by ?? '—');
            setText(modal, 'issued_at', c.issued_at);
            setText(modal, 'revoked_at', c.revoked_at);
            setText(modal, 'revoked_reason', c.revoked_reason);
            setText(modal, 'verification_url', c.verification_url);

            modal.querySelector('[data-revoked-block]').hidden = c.status !== 'revoked';
            modal.querySelector('[data-show-download]').href = routeFor(urls.download, c.id);

            const badge = modal.querySelector('[data-show-status]');
            badge.textContent = i18n.statuses[c.status];
            badge.className = `badge badge--${c.status === 'valid' ? 'success' : 'danger'}`;
        },

        onAction(action, id) {
            if (action === 'revoke') openRevoke(id);
            if (action === 'reinstate') run(() => reinstate(id));
        },
    });

    function run(fn) {
        fn().catch((err) => handleError(err));
    }
}