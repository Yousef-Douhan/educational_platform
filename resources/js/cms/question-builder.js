import {
    http, toast, confirmDialog, Modal, setLoading, clearFieldErrors, handleError, routeFor,
} from './crud-common';
import { el, icon, badge, fmtDateTime } from './dom';

const TYPE_ICONS = { multiple_choice: 'fa-list-ul', true_false: 'fa-toggle-on', text: 'fa-keyboard' };
const MAX_MCQ = 8;
const MAX_TEXT = 10;

export function initQuestionBuilder(app) {
    const root = app.querySelector('#builderModal');
    const qModal = app.querySelector('#questionModal');
    if (!root || !qModal) return null;

    const urls = JSON.parse(root.dataset.urls);
    const i18n = JSON.parse(root.dataset.i18n);

    const form = qModal.querySelector('#questionForm');
    const bodyEl = root.querySelector('.crud-modal__body');
    const listEl = root.querySelector('[data-qb-list]');
    const trashEl = root.querySelector('[data-qb-trash]');
    const bannerEl = root.querySelector('[data-qb-banner]');
    const addQuestionBtn = root.querySelector('[data-qb="add-question"]');
    const trashLabel = root.querySelector('[data-qb-trash-label]');

    const typeSel = form.querySelector('[data-q-type]');
    const pointsInput = form.elements.points;
    const textArea = form.elements.question_text;
    const sections = [...form.querySelectorAll('[data-section]')];
    const mcqList = form.querySelector('[data-mcq-list]');
    const textList = form.querySelector('[data-text-list]');
    const lockNote = form.querySelector('[data-q-lock-note]');
    const addMcq = form.querySelector('[data-qb-add="mcq"]');
    const addText = form.querySelector('[data-qb-add="text"]');
    const tfRadios = [...form.querySelectorAll('input[name="tf_answer"]')];

    const state = { quizId: null, data: null, trashOpen: false, trashRows: [], question: null, locked: false };

    const run = (fn) => fn().catch((err) => handleError(err));
    const findQuestion = (id) => state.data.questions.find((q) => q.id === Number(id));

    /* ---------- التحميل ---------- */
    async function load() {
        const { data } = await http.get(routeFor(urls.tree, state.quizId));
        state.data = data;
        if (state.trashOpen) await loadTrash();
        render();
    }

    async function open(quizId) {
        state.quizId = quizId;
        state.trashOpen = false;
        try {
            await load();
            Modal.open(root);
        } catch (err) { handleError(err); }
    }

    /* ---------- عرض القائمة ---------- */
    function render() {
        const { data } = state;
        const top = bodyEl.scrollTop;

        root.querySelector('[data-qb-subtitle]').textContent = `${data.quiz.title_ar} — ${data.quiz.title_en}`;
        root.querySelector('[data-qb-stat="questions"]').textContent = data.totals.questions;
        root.querySelector('[data-qb-stat="points"]').textContent = data.totals.points;
        root.querySelector('[data-qb-stat="pass_mark"]').textContent = `${data.quiz.pass_mark}%`;
        bannerEl.hidden = !data.locked;

        listEl.replaceChildren();
        if (!data.questions.length) listEl.append(el('div', 'qb-empty', i18n.no_questions));
        data.questions.forEach((q, i) => listEl.append(renderQuestion(q, i, data.questions.length)));

        renderTrash();
        listEl.hidden = state.trashOpen;
        trashEl.hidden = !state.trashOpen;
        trashLabel.textContent = state.trashOpen ? i18n.back_label : `${i18n.trash_label} (${data.totals.trashed})`;
        addQuestionBtn.hidden = state.trashOpen || !data.can_edit;
        bodyEl.scrollTop = top;
    }

    function qbBtn(iconName, label, key, data = {}, { danger = false, disabled = false } = {}) {
        const b = el('button', `qb-btn${danger ? ' qb-btn--danger' : ''}`);
        b.type = 'button';
        b.dataset.qb = key;
        Object.entries(data).forEach(([k, v]) => { b.dataset[k] = v; });
        b.title = label;
        b.setAttribute('aria-label', label);
        b.disabled = disabled;
        b.append(icon(iconName));

        return b;
    }

    function optionsPreview(q) {
        if (q.question_type === 'text') {
            const wrap = el('div', 'qb-answers');
            q.options.forEach((o) => wrap.append(el('span', 'qb-answer', o.option_text)));

            return wrap;
        }

        const ul = el('ul', 'qb-opts');
        q.options.forEach((o, i) => {
            const li = el('li', `qb-opt${o.is_correct ? ' is-correct' : ''}`);
            const label = q.question_type === 'true_false' ? i18n.tf[o.option_text] : o.option_text;
            li.append(el('span', 'qb-opt__letter', String.fromCharCode(65 + i)), el('span', 'qb-opt__text', label));
            if (o.is_correct) li.append(icon('fa-check'));
            ul.append(li);
        });

        return ul;
    }

    function renderQuestion(q, index, count) {
        const card = el('article', 'qb-card');
        const head = el('header', 'qb-card__head');

        const title = el('div', 'qb-card__title');
        const meta = el('div', 'qb-meta');
        meta.append(badge(i18n.types[q.question_type], 'info', TYPE_ICONS[q.question_type]), badge(`${q.points} ${i18n.points_short}`, 'primary'));
        title.append(el('p', '', q.question_text), meta);

        const actions = el('div', 'qb-actions');
        if (state.data.can_edit) {
            actions.append(
                qbBtn('fa-arrow-up', i18n.move_up, 'q-up', { id: q.id }, { disabled: index === 0 }),
                qbBtn('fa-arrow-down', i18n.move_down, 'q-down', { id: q.id }, { disabled: index === count - 1 }),
                qbBtn('fa-pen', i18n.edit, 'q-edit', { id: q.id }),
                qbBtn('fa-trash-can', i18n.delete, 'q-delete', { id: q.id }, { danger: true }),
            );
        }

        head.append(el('span', 'qb-card__no', String(index + 1)), title, actions);
        card.append(head, optionsPreview(q));

        return card;
    }

    /* ---------- نموذج السؤال ---------- */
    function mcqRow(opt = {}) {
        const row = el('div', 'opt-row');
        row.dataset.optionId = opt.id ?? '';

        const radio = el('input');
        radio.type = 'radio';
        radio.name = 'correct';
        radio.checked = Boolean(opt.is_correct);
        radio.setAttribute('aria-label', i18n.mark_correct);
        radio.title = i18n.mark_correct;

        const text = el('input', 'form-control');
        text.type = 'text';
        text.maxLength = 1000;
        text.value = opt.option_text ?? '';
        text.placeholder = i18n.option_placeholder;

        row.append(radio, text, removeBtn());

        return row;
    }

    function textRow(opt = {}) {
        const row = el('div', 'opt-row');
        row.dataset.optionId = opt.id ?? '';
        row.style.gridTemplateColumns = '1fr auto';

        const text = el('input', 'form-control');
        text.type = 'text';
        text.maxLength = 1000;
        text.value = opt.option_text ?? '';
        text.placeholder = i18n.answer_placeholder;

        row.append(text, removeBtn());

        return row;
    }

    function removeBtn() {
        const b = el('button', 'opt-remove');
        b.type = 'button';
        b.dataset.optRemove = '';
        b.setAttribute('aria-label', i18n.remove);
        b.append(icon('fa-trash-can'));

        return b;
    }

    function showSection() {
        sections.forEach((s) => { s.hidden = s.dataset.section !== typeSel.value; });
    }

    function applyLock() {
        const locked = state.locked;
        typeSel.disabled = locked;
        pointsInput.disabled = locked;
        addMcq.disabled = locked;
        addText.disabled = locked;
        tfRadios.forEach((r) => { r.disabled = locked; });
        form.querySelectorAll('.opt-row input[type="radio"], [data-opt-remove]').forEach((n) => { n.disabled = locked; });
        lockNote.hidden = !locked;
    }

    function openQuestion(question = null) {
        state.question = question;
        state.locked = Boolean(question && state.data.locked);

        form.reset();
        clearFieldErrors(form);
        typeSel.replaceChildren(...Object.entries(i18n.types).map(([v, label]) => new Option(label, v)));
        typeSel.value = question?.question_type || 'multiple_choice';
        textArea.value = question?.question_text ?? '';
        pointsInput.value = question?.points ?? 1;

        mcqList.replaceChildren();
        textList.replaceChildren();

        if (question?.question_type === 'multiple_choice') question.options.forEach((o) => mcqList.append(mcqRow(o)));
        else mcqList.append(mcqRow({ is_correct: true }), mcqRow());

        if (question?.question_type === 'text') question.options.forEach((o) => textList.append(textRow(o)));
        else textList.append(textRow());

        const tf = question?.question_type === 'true_false' ? question.options.find((o) => o.is_correct)?.option_text : 'true';
        tfRadios.forEach((r) => { r.checked = r.value === (tf || 'true'); });

        showSection();
        applyLock();
        qModal.querySelector('#questionModalTitle').textContent = question ? i18n.question_edit_title : i18n.question_create_title;
        Modal.open(qModal);
        textArea.focus();
    }

    /** يحذف الصفوف الفارغة ويعيد ترقيم الأسماء ليطابق فهارس أخطاء الخادم */
    function collectRows(list, withCorrect) {
        list.querySelectorAll('.opt-row').forEach((row) => {
            if (!row.querySelector('input[type="text"]').value.trim()) row.remove();
        });

        return [...list.querySelectorAll('.opt-row')].map((row, i) => {
            const text = row.querySelector('input[type="text"]');
            text.name = `options[${i}][option_text]`;
            const item = { option_text: text.value };
            if (row.dataset.optionId) item.id = Number(row.dataset.optionId);
            if (withCorrect) item.is_correct = row.querySelector('input[type="radio"]').checked;

            return item;
        });
    }

    function buildPayload() {
        const type = typeSel.value;
        const payload = { question_text: textArea.value, question_type: type, points: Number(pointsInput.value) };

        if (type === 'true_false') payload.tf_answer = tfRadios.find((r) => r.checked)?.value;
        if (type === 'multiple_choice') payload.options = collectRows(mcqList, true);
        if (type === 'text') payload.options = collectRows(textList, false);

        return payload;
    }

    async function saveQuestion() {
        const btn = form.querySelector('[type=submit]');
        const payload = buildPayload();
        clearFieldErrors(form);
        setLoading(btn, true);
        try {
            const { data } = state.question
                ? await http.put(routeFor(urls.update, state.question.id), payload)
                : await http.post(routeFor(urls.store, state.quizId), payload);
            Modal.close(qModal);
            toast('success', data.message);
            await load();
        } catch (err) { handleError(err, form); }
        finally { setLoading(btn, false); }
    }

    /* ---------- الحذف والترتيب ---------- */
    async function deleteQuestion(id) {
        const ok = await confirmDialog({
            title: i18n.delete_title, text: i18n.delete_text,
            confirmText: i18n.confirm_delete, cancelText: i18n.cancel,
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.destroy, id));
        toast('success', data.message);
        await load();
    }

    async function moveQuestion(id, dir) {
        const ids = state.data.questions.map((q) => q.id);
        const i = ids.indexOf(Number(id));
        const j = i + dir;
        if (i < 0 || j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        await http.post(routeFor(urls.reorder, state.quizId), { ids });
        await load();
    }

    /* ---------- سلة المحذوفات ---------- */
    async function loadTrash() {
        const { data } = await http.get(routeFor(urls.trashed, state.quizId));
        state.trashRows = data.data;
    }

    function renderTrash() {
        trashEl.replaceChildren();
        if (!state.trashRows.length) {
            trashEl.append(el('div', 'qb-empty', i18n.no_trash));
            return;
        }

        state.trashRows.forEach((q) => {
            const row = el('div', 'qb-trash-row');
            const title = el('div', 'qb-card__title');
            const meta = el('div', 'qb-meta');
            meta.append(
                badge(i18n.types[q.question_type], 'info', TYPE_ICONS[q.question_type]),
                badge(`${i18n.deleted_at}: ${fmtDateTime(q.deleted_at)}`, 'danger', 'fa-clock'),
            );
            title.append(el('p', '', q.question_text), meta);

            const actions = el('div', 'qb-trash-actions');
            if (state.data.can_edit) {
                const restore = el('button', 'btn btn-success-soft btn-sm');
                restore.type = 'button';
                restore.dataset.qb = 'q-restore';
                restore.dataset.id = q.id;
                restore.append(icon('fa-rotate-left'), document.createTextNode(` ${i18n.restore}`));
                actions.append(restore);
            }
            if (state.data.can_force_delete) {
                const force = el('button', 'btn btn-danger-soft btn-sm');
                force.type = 'button';
                force.dataset.qb = 'q-force';
                force.dataset.id = q.id;
                force.append(icon('fa-skull-crossbones'), document.createTextNode(` ${i18n.force_delete}`));
                actions.append(force);
            }

            row.append(title, actions);
            trashEl.append(row);
        });
    }

    async function toggleTrash() {
        state.trashOpen = !state.trashOpen;
        if (state.trashOpen) await loadTrash();
        render();
    }

    async function restoreQuestion(id) {
        const ok = await confirmDialog({
            title: i18n.restore_title, text: i18n.restore_text,
            confirmText: i18n.restore_confirm, cancelText: i18n.cancel, icon: 'question', danger: false,
        });
        if (!ok) return;
        const { data } = await http.patch(routeFor(urls.restore, id));
        toast('success', data.message);
        await load();
    }

    async function forceDeleteQuestion(id) {
        const ok = await confirmDialog({
            title: i18n.force_title, text: i18n.force_text,
            confirmText: i18n.force_confirm, cancelText: i18n.cancel, icon: 'error',
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.force, id));
        toast('success', data.message);
        await load();
    }

    /* ---------- الأحداث ---------- */
    app.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-qb]');
        if (!btn || btn.disabled) return;

        const { qb, id } = btn.dataset;
        switch (qb) {
            case 'add-question': return openQuestion(null);
            case 'q-edit': return openQuestion(findQuestion(id));
            case 'q-delete': return run(() => deleteQuestion(id));
            case 'q-up': return run(() => moveQuestion(id, -1));
            case 'q-down': return run(() => moveQuestion(id, 1));
            case 'toggle-trash': return run(toggleTrash);
            case 'q-restore': return run(() => restoreQuestion(id));
            case 'q-force': return run(() => forceDeleteQuestion(id));
            default: return undefined;
        }
    });

    form.addEventListener('click', (e) => {
        if (e.target.closest('[data-qb-add="mcq"]')) {
            if (mcqList.children.length < MAX_MCQ) mcqList.append(mcqRow());
            return;
        }
        if (e.target.closest('[data-qb-add="text"]')) {
            if (textList.children.length < MAX_TEXT) textList.append(textRow());
            return;
        }
        const remove = e.target.closest('[data-opt-remove]');
        if (remove && !remove.disabled) remove.closest('.opt-row').remove();
    });

    typeSel.addEventListener('change', showSection);
    form.addEventListener('submit', (e) => { e.preventDefault(); saveQuestion(); });

    return { open };
}