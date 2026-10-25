import {
    http, toast, confirmDialog, Modal, setLoading, clearFieldErrors, handleError, routeFor,
} from './crud-common';

const TYPE_ICONS = {
    video: 'fa-circle-play', article: 'fa-file-lines', document: 'fa-file-pdf',
    quiz: 'fa-circle-question', live: 'fa-tower-broadcast',
};

const MAX_UPLOAD = 20 * 1024 * 1024;

function el(tag, className = '', text = null) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
}

function icon(name) {
    return el('i', `fa-solid ${name}`);
}

function badge(text, tone, iconName = null) {
    const b = el('span', `badge badge--${tone}`);
    if (iconName) b.append(icon(iconName), document.createTextNode(' '));
    b.append(document.createTextNode(text));
    return b;
}

const fmtBytes = (n) => {
    if (n === null || n === undefined) return '';
    if (n < 1024) return `${n} B`;
    if (n < 1048576) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / 1048576).toFixed(1)} MB`;
};

const fmtDateTime = (iso) => (iso ? new Date(iso).toLocaleString(document.documentElement.lang || undefined) : '');

function iconBtn(iconName, label, cur, data = {}, { danger = false, disabled = false } = {}) {
    const b = el('button', `cur-btn${danger ? ' cur-btn--danger' : ''}`);
    b.type = 'button';
    b.dataset.cur = cur;
    Object.entries(data).forEach(([k, v]) => { b.dataset[k] = v; });
    b.title = label;
    b.setAttribute('aria-label', label);
    b.disabled = disabled;
    b.append(icon(iconName));
    return b;
}

function titles(ar, en) {
    const box = el('div', 'cur-titles');
    const a = el('strong', '', ar);
    a.dir = 'rtl';
    const e = el('small', '', en);
    e.dir = 'ltr';
    box.append(a, e);
    return box;
}

export function initCurriculum(app) {
    const root = app.querySelector('#curriculumModal');
    const sectionModal = app.querySelector('#sectionModal');
    const lessonModal = app.querySelector('#lessonModal');
    if (!root || !sectionModal || !lessonModal) return null;

    const urls = JSON.parse(root.dataset.urls);
    const i18n = JSON.parse(root.dataset.i18n);

    const sectionForm = sectionModal.querySelector('#sectionForm');
    const lessonForm = lessonModal.querySelector('#lessonForm');
    const bodyEl = root.querySelector('.crud-modal__body');
    const treeEl = root.querySelector('[data-cur-tree]');
    const trashEl = root.querySelector('[data-cur-trash]');
    const trashLabel = root.querySelector('[data-cur-trash-label]');
    const addSectionBtn = root.querySelector('[data-cur="add-section"]');

    const typeSelect = lessonForm.querySelector('[data-lesson-type]');
    const providerSelect = lessonForm.querySelector('[data-lesson-provider]');
    const videoUrl = lessonForm.querySelector('[data-lesson-video-url]');
    const sectionSelect = lessonForm.querySelector('[data-lesson-section]');
    const sectionWrap = lessonForm.querySelector('[data-lesson-section-wrap]');
    const hintWrap = lessonForm.querySelector('[data-type-hint-wrap]');
    const hintText = lessonForm.querySelector('[data-type-hint]');
    const attLocked = lessonForm.querySelector('[data-att-locked]');
    const attBody = lessonForm.querySelector('[data-att-body]');
    const attList = lessonForm.querySelector('[data-att-list]');
    const attFile = lessonForm.querySelector('[data-att-file]');
    const attPick = lessonForm.querySelector('[data-att-pick]');

    const state = {
        courseId: null, data: null, trashOpen: false, trashRows: [],
        section: null, lesson: null, sectionId: null, attachments: [],
    };

    const run = (fn) => fn().catch((err) => handleError(err));
    const findSection = (id) => state.data.sections.find((s) => s.id === Number(id));

    /* ---------- التحميل والعرض ---------- */
    async function load() {
        const { data } = await http.get(routeFor(urls.tree, state.courseId));
        state.data = data;
        if (state.trashOpen) await loadTrash();
        render();
    }

    async function open(courseId) {
        state.courseId = courseId;
        state.trashOpen = false;
        try {
            await load();
            Modal.open(root);
        } catch (err) { handleError(err); }
    }

    function render() {
        const { data } = state;
        const top = bodyEl.scrollTop;

        root.querySelector('[data-cur-course]').textContent = `${data.course.title_ar} — ${data.course.title_en}`;
        Object.entries(data.totals).forEach(([key, value]) => {
            root.querySelectorAll(`[data-cur-stat="${key}"]`).forEach((n) => { n.textContent = value; });
        });

        treeEl.replaceChildren();
        if (!data.sections.length) {
            treeEl.append(el('div', 'cur-empty', i18n.no_sections));
        }
        data.sections.forEach((s, i) => treeEl.append(renderSection(s, i, data.sections.length)));

        renderTrash();
        applyView();
        bodyEl.scrollTop = top;
    }

    function applyView() {
        treeEl.hidden = state.trashOpen;
        trashEl.hidden = !state.trashOpen;
        trashLabel.textContent = state.trashOpen
            ? i18n.back_label
            : `${i18n.trash_label} (${state.data?.totals.trashed ?? 0})`;
        addSectionBtn.hidden = state.trashOpen || !state.data?.can_edit;
    }

    function renderSection(section, index, count) {
        const canEdit = state.data.can_edit;
        const box = el('article', 'cur-section');

        const head = el('header', 'cur-section__head');
        const actions = el('div', 'cur-actions');
        if (canEdit) {
            actions.append(
                iconBtn('fa-arrow-up', i18n.move_up, 'section-up', { id: section.id }, { disabled: index === 0 }),
                iconBtn('fa-arrow-down', i18n.move_down, 'section-down', { id: section.id }, { disabled: index === count - 1 }),
                iconBtn('fa-plus', i18n.add_lesson, 'add-lesson', { id: section.id }),
                iconBtn('fa-pen', i18n.edit, 'edit-section', { id: section.id }),
                iconBtn('fa-trash-can', i18n.delete, 'delete-section', { id: section.id }, { danger: true }),
            );
        }
        head.append(el('span', 'cur-section__no', String(index + 1)), titles(section.title_ar, section.title_en), actions);

        const list = el('ul', 'cur-lessons');
        if (!section.lessons.length) {
            const empty = el('li', 'cur-empty', i18n.no_lessons);
            list.append(empty);
        }
        section.lessons.forEach((l, i) => list.append(renderLesson(l, i, section.lessons.length, section.id)));

        box.append(head, list);
        return box;
    }

    function renderLesson(lesson, index, count, sectionId) {
        const canEdit = state.data.can_edit;
        const row = el('li', 'cur-lesson');

        const ic = el('span', 'cur-lesson__icon');
        ic.append(icon(TYPE_ICONS[lesson.content_type] || 'fa-file'));

        const info = titles(lesson.title_ar, lesson.title_en);
        const meta = el('div', 'cur-lesson__meta');
        meta.append(badge(i18n.types[lesson.content_type], 'info'));
        meta.append(lesson.is_published ? badge(i18n.published_badge, 'success') : badge(i18n.draft_badge, 'muted'));
        if (lesson.is_free_preview) meta.append(badge(i18n.free_badge, 'primary', 'fa-eye'));
        if (lesson.duration_minutes > 0) meta.append(badge(`${lesson.duration_minutes} ${i18n.minutes_short}`, 'muted', 'fa-clock'));
        if (lesson.attachments_count > 0) meta.append(badge(String(lesson.attachments_count), 'muted', 'fa-paperclip'));
        info.append(meta);

        const actions = el('div', 'cur-actions');
        if (canEdit) {
            actions.append(
                iconBtn('fa-arrow-up', i18n.move_up, 'lesson-up', { id: lesson.id, section: sectionId }, { disabled: index === 0 }),
                iconBtn('fa-arrow-down', i18n.move_down, 'lesson-down', { id: lesson.id, section: sectionId }, { disabled: index === count - 1 }),
                iconBtn('fa-pen', i18n.edit, 'edit-lesson', { id: lesson.id }),
                iconBtn('fa-trash-can', i18n.delete, 'delete-lesson', { id: lesson.id }, { danger: true }),
            );
        }

        row.append(ic, info, actions);
        return row;
    }

    /* ---------- الأقسام ---------- */
    function openSection(section) {
        state.section = section;
        sectionForm.reset();
        clearFieldErrors(sectionForm);
        if (section) {
            sectionForm.elements.title_ar.value = section.title_ar;
            sectionForm.elements.title_en.value = section.title_en;
        }
        sectionModal.querySelector('#sectionModalTitle').textContent = section ? i18n.section_edit_title : i18n.section_create_title;
        Modal.open(sectionModal);
    }

    async function saveSection() {
        const btn = sectionForm.querySelector('[type=submit]');
        const payload = { title_ar: sectionForm.elements.title_ar.value, title_en: sectionForm.elements.title_en.value };
        clearFieldErrors(sectionForm);
        setLoading(btn, true);
        try {
            const { data } = state.section
                ? await http.put(routeFor(urls.sectionUpdate, state.section.id), payload)
                : await http.post(routeFor(urls.sectionStore, state.courseId), payload);
            Modal.close(sectionModal);
            toast('success', data.message);
            await load();
        } catch (err) { handleError(err, sectionForm); }
        finally { setLoading(btn, false); }
    }

    async function deleteSection(id) {
        const ok = await confirmDialog({
            title: i18n.section_delete_title, text: i18n.section_delete_text,
            confirmText: i18n.confirm_delete, cancelText: i18n.cancel,
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.sectionDestroy, id));
        toast('success', data.message);
        await load();
    }

    async function moveSection(id, dir) {
        const ids = state.data.sections.map((s) => s.id);
        const i = ids.indexOf(Number(id));
        const j = i + dir;
        if (i < 0 || j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        await http.post(routeFor(urls.sectionsReorder, state.courseId), { ids });
        await load();
    }

    /* ---------- الدروس ---------- */
    function toggleGroups() {
        const type = typeSelect.value;
        lessonForm.querySelectorAll('[data-group]').forEach((g) => { g.hidden = g.dataset.group !== type; });
        const hint = i18n.type_hints[type] || '';
        hintText.textContent = hint;
        hintWrap.hidden = !hint;
    }

    function updateProviderHint() {
        videoUrl.placeholder = i18n.provider_hints[providerSelect.value] || '';
    }

    function renderAttachments() {
        attList.replaceChildren();
        if (!state.attachments.length) {
            attList.append(el('div', 'cur-empty', i18n.no_attachments));
            return;
        }
        state.attachments.forEach((a) => {
            const item = el('div', 'att-item');
            const link = el('a', 'att-item__name', a.title);
            link.href = a.download_url;
            link.setAttribute('download', '');
            item.append(icon('fa-paperclip'), link, el('span', 'att-item__size', fmtBytes(a.file_size)),
                iconBtn('fa-trash-can', i18n.delete, 'att-delete', { id: a.id }, { danger: true }));
            attList.append(item);
        });
    }

    function openLesson({ lesson = null, sectionId = null, sections = [], attachments = [] }) {
        const isEdit = Boolean(lesson);
        state.lesson = lesson;
        state.sectionId = sectionId;
        state.attachments = attachments;

        lessonForm.reset();
        clearFieldErrors(lessonForm);

        typeSelect.replaceChildren(...Object.entries(i18n.types).map(([v, label]) => new Option(label, v)));
        providerSelect.replaceChildren(new Option(i18n.select_provider, ''), ...Object.entries(i18n.providers).map(([v, label]) => new Option(label, v)));

        sectionWrap.hidden = !isEdit;
        sectionSelect.disabled = !isEdit; // لا يُرسل عند الإنشاء
        sectionSelect.replaceChildren(...sections.map((s) => new Option(`${s.title_ar} — ${s.title_en}`, s.id, false, isEdit && s.id === lesson.section_id)));

        typeSelect.value = lesson?.content_type || 'video';
        providerSelect.value = lesson?.video_provider || '';
        ['title_ar', 'title_en', 'video_url', 'content_ar', 'content_en'].forEach((f) => { lessonForm.elements[f].value = lesson?.[f] ?? ''; });
        lessonForm.elements.duration_minutes.value = lesson?.duration_minutes ?? '';
        lessonForm.elements.is_free_preview.checked = Boolean(lesson?.is_free_preview);
        lessonForm.elements.is_published.checked = Boolean(lesson?.is_published);

        toggleGroups();
        updateProviderHint();

        attLocked.hidden = isEdit;
        attBody.hidden = !isEdit;
        if (isEdit) renderAttachments();

        lessonModal.querySelector('#lessonModalTitle').textContent = isEdit ? i18n.lesson_edit_title : i18n.lesson_create_title;
        Modal.open(lessonModal);
    }

    async function editLesson(id) {
        const { data } = await http.get(routeFor(urls.lessonShow, id));
        openLesson({ lesson: data.data, sections: data.sections, attachments: data.attachments });
    }

    async function saveLesson() {
        const btn = lessonForm.querySelector('[type=submit]');
        const payload = Object.fromEntries(new FormData(lessonForm).entries());
        payload.is_free_preview = lessonForm.elements.is_free_preview.checked;
        payload.is_published = lessonForm.elements.is_published.checked;

        clearFieldErrors(lessonForm);
        setLoading(btn, true);
        try {
            const { data } = state.lesson
                ? await http.put(routeFor(urls.lessonUpdate, state.lesson.id), payload)
                : await http.post(routeFor(urls.lessonStore, state.sectionId), payload);
            Modal.close(lessonModal);
            toast('success', data.message);
            await load();
        } catch (err) { handleError(err, lessonForm); }
        finally { setLoading(btn, false); }
    }

    async function deleteLesson(id) {
        const ok = await confirmDialog({
            title: i18n.lesson_delete_title, text: i18n.lesson_delete_text,
            confirmText: i18n.confirm_delete, cancelText: i18n.cancel,
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.lessonDestroy, id));
        toast('success', data.message);
        await load();
    }

    async function moveLesson(sectionId, id, dir) {
        const section = findSection(sectionId);
        const ids = section.lessons.map((l) => l.id);
        const i = ids.indexOf(Number(id));
        const j = i + dir;
        if (i < 0 || j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        await http.post(routeFor(urls.lessonsReorder, section.id), { ids });
        await load();
    }

    /* ---------- المرفقات ---------- */
    async function uploadAttachment(file) {
        if (file.size > MAX_UPLOAD) {
            toast('error', i18n.file_too_large);
            return;
        }
        const fd = new FormData();
        fd.append('file', file);
        clearFieldErrors(lessonForm);
        attPick.classList.add('is-loading');
        try {
            const { data } = await http.post(routeFor(urls.attStore, state.lesson.id), fd);
            state.attachments.push(data.data);
            renderAttachments();
            toast('success', data.message);
            await load(); // لتحديث عدّاد المرفقات في الشجرة
        } catch (err) { handleError(err, lessonForm); }
        finally { attPick.classList.remove('is-loading'); }
    }

    async function deleteAttachment(id) {
        const ok = await confirmDialog({
            title: i18n.att_delete_title, text: i18n.att_delete_text,
            confirmText: i18n.confirm_delete, cancelText: i18n.cancel,
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.attDestroy, id));
        state.attachments = state.attachments.filter((a) => a.id !== Number(id));
        renderAttachments();
        toast('success', data.message);
        await load();
    }

    /* ---------- سلة محذوفات الدروس ---------- */
    async function loadTrash() {
        const { data } = await http.get(routeFor(urls.trashed, state.courseId));
        state.trashRows = data.data;
    }

    function renderTrash() {
        trashEl.replaceChildren();
        if (!state.trashRows.length) {
            trashEl.append(el('div', 'cur-empty', i18n.no_trash));
            return;
        }
        state.trashRows.forEach((l) => {
            const row = el('div', 'cur-trash-row');
            const ic = el('span', 'cur-lesson__icon');
            ic.append(icon(TYPE_ICONS[l.content_type] || 'fa-file'));

            const info = titles(l.title_ar, l.title_en);
            const meta = el('div', 'cur-lesson__meta');
            meta.append(
                badge(`${i18n.in_section}: ${l.section?.title_ar ?? '—'}`, 'info'),
                badge(`${i18n.deleted_at}: ${fmtDateTime(l.deleted_at)}`, 'danger', 'fa-clock'),
            );
            info.append(meta);

            const actions = el('div', 'cur-trash-actions');
            if (state.data.can_edit) {
                const restore = el('button', 'btn btn-success-soft btn-sm');
                restore.type = 'button';
                restore.dataset.cur = 'restore-lesson';
                restore.dataset.id = l.id;
                restore.append(icon('fa-rotate-left'), document.createTextNode(` ${i18n.restore}`));
                actions.append(restore);
            }
            if (state.data.can_force_delete) {
                const force = el('button', 'btn btn-danger-soft btn-sm');
                force.type = 'button';
                force.dataset.cur = 'force-lesson';
                force.dataset.id = l.id;
                force.append(icon('fa-skull-crossbones'), document.createTextNode(` ${i18n.force_delete}`));
                actions.append(force);
            }

            row.append(ic, info, actions);
            trashEl.append(row);
        });
    }

    async function toggleTrash() {
        state.trashOpen = !state.trashOpen;
        if (state.trashOpen) await loadTrash();
        render();
    }

    async function restoreLesson(id) {
        const ok = await confirmDialog({
            title: i18n.restore_title, text: i18n.restore_text,
            confirmText: i18n.restore_confirm, cancelText: i18n.cancel, icon: 'question', danger: false,
        });
        if (!ok) return;
        const { data } = await http.patch(routeFor(urls.lessonRestore, id));
        toast('success', data.message);
        await load();
    }

    async function forceDeleteLesson(id) {
        const ok = await confirmDialog({
            title: i18n.force_title, text: i18n.force_text,
            confirmText: i18n.force_confirm, cancelText: i18n.cancel, icon: 'error',
        });
        if (!ok) return;
        const { data } = await http.delete(routeFor(urls.lessonForce, id));
        toast('success', data.message);
        await load();
    }

    /* ---------- الأحداث ---------- */
    app.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-cur]');
        if (!btn || btn.disabled) return;

        const { cur, id, section } = btn.dataset;
        switch (cur) {
            case 'add-section': return openSection(null);
            case 'edit-section': return openSection(findSection(id));
            case 'delete-section': return run(() => deleteSection(id));
            case 'section-up': return run(() => moveSection(id, -1));
            case 'section-down': return run(() => moveSection(id, 1));
            case 'add-lesson': return run(async () => {
                const s = findSection(id);
                openLesson({ sectionId: s.id, sections: state.data.sections });
            });
            case 'edit-lesson': return run(() => editLesson(id));
            case 'delete-lesson': return run(() => deleteLesson(id));
            case 'lesson-up': return run(() => moveLesson(section, id, -1));
            case 'lesson-down': return run(() => moveLesson(section, id, 1));
            case 'att-delete': return run(() => deleteAttachment(id));
            case 'toggle-trash': return run(toggleTrash);
            case 'restore-lesson': return run(() => restoreLesson(id));
            case 'force-lesson': return run(() => forceDeleteLesson(id));
            default: return undefined;
        }
    });

    sectionForm.addEventListener('submit', (e) => { e.preventDefault(); saveSection(); });
    lessonForm.addEventListener('submit', (e) => { e.preventDefault(); saveLesson(); });
    typeSelect.addEventListener('change', toggleGroups);
    providerSelect.addEventListener('change', updateProviderHint);

    attFile.addEventListener('change', () => {
        const file = attFile.files[0];
        attFile.value = '';
        if (file && state.lesson) uploadAttachment(file);
    });

    return { open };
}