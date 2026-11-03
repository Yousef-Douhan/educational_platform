import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor, Modal, setLoading, clearFieldErrors } from './crud-common';

const app = document.getElementById('attemptsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const VOID_URL = app.dataset.voidUrl;
    const TONE = { in_progress: 'info', submitted: 'success', expired: 'warning', voided: 'muted' };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const pct = (v) => `${Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })}%`;

    /* إجابات الطالب: نصوص أسئلة وإجابات من المستخدمين، فتُعرض بـ textContent فقط */
    function renderAnswers(container, answers, graded) {
        container.replaceChildren();

        if (!answers.length) {
            container.append(el('p', 'text-muted', i18n.no_answers));
            return;
        }

        answers.forEach((a, i) => {
            const row = el('div', 'attempt-answer');
            if (graded && a.is_correct !== null) row.classList.add(a.is_correct ? 'is-correct' : 'is-wrong');

            const head = el('div', 'attempt-answer__head');
            head.append(el('span', 'attempt-answer__no', `${i + 1}`));
            head.append(el('span', 'attempt-answer__q', a.question));
            if (a.question_deleted) head.append(el('span', 'badge badge--muted', i18n.question_deleted));
            row.append(head);

            const given = el('div', 'attempt-answer__line');
            given.append(el('strong', '', `${i18n.student_answer}: `));
            given.append(el('span', '', a.given || i18n.no_answer));
            row.append(given);

            if (graded && a.is_correct === false && a.correct.length) {
                const right = el('div', 'attempt-answer__line text-muted');
                right.append(el('strong', '', `${i18n.correct_answer}: `));
                right.append(el('span', '', a.correct.join(' / ')));
                row.append(right);
            }

            if (graded) {
                row.append(el('div', 'attempt-answer__points', `${a.awarded}/${a.points} ${i18n.points}`));
            }

            container.append(row);
        });
    }

    /* ---------- نافذة الإلغاء ---------- */
    const voidModal = app.querySelector('#voidModal');
    const voidForm = app.querySelector('#voidForm');
    let currentId = null;

    voidForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentId) return;

        const btn = voidForm.querySelector('[type=submit]');
        clearFieldErrors(voidForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(routeFor(VOID_URL, currentId), new FormData(voidForm));
            Modal.close(voidModal);
            toast('success', data.message);
            await crud.loadRecords();
        } catch (err) {
            handleError(err, voidForm);
        } finally {
            setLoading(btn, false);
        }
    });

    const crud = createCrudModule(app, {
        onShow(modal, payload) {
            const a = payload.data;
            const dash = (v) => (v === null || v === undefined || v === '' ? null : v);

            setText(modal, 'name', a.user.name);
            setText(modal, 'email', a.user.email);
            setText(modal, 'quiz', a.quiz[i18n.title_key]);
            setText(modal, 'course', a.course[i18n.title_key]);
            setText(modal, 'number', `#${a.number}`);
            setText(modal, 'pass_mark', `${a.pass_mark}%`);
            setText(modal, 'started_at', a.started_at);
            setText(modal, 'submitted_at', dash(a.submitted_at));
            setText(modal, 'expires_at', dash(a.expires_at));
            setText(modal, 'duration', a.duration_minutes === null ? null : `${a.duration_minutes} ${i18n.minutes}`);
            setText(modal, 'score', a.graded ? `${pct(a.score)} (${a.points.earned}/${a.points.total}) · ${a.is_passed ? i18n.passed : i18n.failed}` : null);

            const badge = modal.querySelector('[data-show-status]');
            badge.textContent = i18n.statuses[a.status];
            badge.className = `badge badge--${TONE[a.status]}`;

            modal.querySelector('[data-voided-only]').hidden = a.status !== 'voided';
            setText(modal, 'voided_by', dash(a.voided_by));
            setText(modal, 'voided_at', dash(a.voided_at));
            setText(modal, 'void_reason', dash(a.void_reason));

            renderAnswers(modal.querySelector('[data-answers]'), a.answers, a.graded);
        },

        onAction(action, id) {
            if (action !== 'void') return;

            currentId = id;
            voidForm.reset();
            clearFieldErrors(voidForm);
            Modal.open(voidModal);
        },
    });
}
