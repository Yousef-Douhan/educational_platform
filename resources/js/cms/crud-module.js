import {
    http, toast, confirmDialog, Modal, setLoading, setMessages, initCommon,
    clearFieldErrors, handleError, debounce, routeFor,
} from './crud-common';

export const fmtDate = (iso) => (iso ? new Date(iso).toLocaleString(document.documentElement.lang || undefined) : '—');

export function setText(scope, key, value) {
    const text = value === null || value === undefined || value === '' ? '—' : value;
    scope.querySelectorAll(`[data-show="${key}"]`).forEach((el) => { el.textContent = text; });
}

export function createCrudModule(app, hooks = {}) {
    const i18n = JSON.parse(app.dataset.i18n);
    setMessages(i18n);
    initCommon();

    const R = {
        index: app.dataset.indexUrl, create: app.dataset.createUrl, store: app.dataset.storeUrl,
        show: app.dataset.showUrl, edit: app.dataset.editUrl, update: app.dataset.updateUrl,
        destroy: app.dataset.destroyUrl, trashed: app.dataset.trashedUrl,
        restore: app.dataset.restoreUrl, forceDelete: app.dataset.forceDeleteUrl,
    };

    const $ = (sel) => app.querySelector(sel);
    const tableContainer = $('#tableContainer');
    const tableCard = $('#tableCard');
    const createModal = $('#createModal');
    const editModal = $('#editModal');
    const showModal = $('#showModal');
    const createForm = $('#createForm');
    const editForm = $('#editForm');
    const clearBtn = $('#clearSearch');
    const filters = [...app.querySelectorAll('[data-filter]')];

    const trashModal = $('#trashModal');
    const trashContainer = $('#trashContainer');
    const trashCard = $('#trashCard');

    const state = { sort: 'id', dir: 'desc', page: 1 };
    const trashState = { search: '', page: 1 };
    filters.forEach((f) => { state[f.dataset.filter] = ''; });

    const clean = (obj) => Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v != null));
    const pageFromLink = (a) => Number(new URL(a.href).searchParams.get('page')) || 1;
    const stepBack = (container, page) => (container.querySelectorAll('tr[data-id]').length === 1 && page > 1 ? page - 1 : page);

    function updateStats(stats) {
        if (!stats) return;
        Object.entries(stats).forEach(([key, value]) => {
            const el = app.querySelector(`[data-stat="${key}"]`);
            if (el) el.textContent = value;
        });
    }

    /* ---------- القائمة الرئيسية ---------- */
    let reqId = 0;
    async function loadRecords() {
        const id = ++reqId;
        tableCard.classList.add('is-loading');
        try {
            const { data } = await http.get(R.index, { params: clean(state) });
            if (id !== reqId) return;
            tableContainer.innerHTML = data.html;
            updateStats(data.stats);
        } catch (err) {
            handleError(err);
        } finally {
            if (id === reqId) tableCard.classList.remove('is-loading');
        }
    }

    function filterRecords() {
        filters.forEach((f) => { state[f.dataset.filter] = f.value.trim(); });
        state.page = 1;
        if (clearBtn) clearBtn.hidden = !state.search;
        loadRecords();
    }

    function resetFilters() {
        filters.forEach((f) => { f.value = ''; });
        filterRecords();
    }

    filters.forEach((f) => {
        if (f.type === 'search') f.addEventListener('input', debounce(filterRecords, 400));
        else f.addEventListener('change', filterRecords);
    });

    /* ---------- إنشاء / تعديل / عرض ---------- */
    async function openCreateModal() {
        try {
            const { data } = await http.get(R.create);
            createForm.reset();
            clearFieldErrors(createForm);
            hooks.onCreate?.(createForm, data);
            Modal.open(createModal);
        } catch (err) { handleError(err); }
    }

    async function storeRecord() {
        hooks.beforeSubmit?.(createForm);
        const btn = createForm.querySelector('[type=submit]');
        clearFieldErrors(createForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(R.store, new FormData(createForm));
            Modal.close(createModal);
            toast('success', data.message);
            state.page = 1;
            await loadRecords();
        } catch (err) { handleError(err, createForm); }
        finally { setLoading(btn, false); }
    }

    async function openEditModal(id) {
        try {
            const { data } = await http.get(routeFor(R.edit, id));
            editForm.reset();
            clearFieldErrors(editForm);
            editForm.dataset.id = data.data.id;
            hooks.onEdit?.(editForm, data);
            Modal.open(editModal);
        } catch (err) { handleError(err); }
    }

    async function updateRecord() {
        hooks.beforeSubmit?.(editForm);
        const btn = editForm.querySelector('[type=submit]');
        const fd = new FormData(editForm);
        fd.append('_method', 'PUT');
        clearFieldErrors(editForm);
        setLoading(btn, true);
        try {
            const { data } = await http.post(routeFor(R.update, editForm.dataset.id), fd);
            Modal.close(editModal);
            toast('success', data.message);
            await loadRecords();
        } catch (err) { handleError(err, editForm); }
        finally { setLoading(btn, false); }
    }

    async function openShowModal(id) {
        try {
            const { data } = await http.get(routeFor(R.show, id));
            hooks.onShow?.(showModal, data);
            Modal.open(showModal);
        } catch (err) { handleError(err); }
    }

    /* ---------- حذف مؤقت ---------- */
    async function confirmDelete(id) {
        const ok = await confirmDialog({
            title: i18n.delete_title, text: i18n.delete_text,
            confirmText: i18n.delete_confirm, cancelText: i18n.cancel,
        });
        if (!ok) return;
        try {
            const { data } = await http.delete(routeFor(R.destroy, id));
            toast('success', data.message);
            state.page = stepBack(tableContainer, state.page);
            await loadRecords();
        } catch (err) { handleError(err); }
    }

    /* ---------- سلة المحذوفات (اختيارية) ---------- */
    let trashReq = 0;
    async function loadTrash() {
        if (!trashModal) return;
        const id = ++trashReq;
        trashCard.classList.add('is-loading');
        try {
            const { data } = await http.get(R.trashed, { params: clean(trashState) });
            if (id !== trashReq) return;
            trashContainer.innerHTML = data.html;
            updateStats(data.stats);
        } catch (err) {
            handleError(err);
        } finally {
            if (id === trashReq) trashCard.classList.remove('is-loading');
        }
    }

    function openTrashModal() {
        if (!trashModal) return;
        trashState.page = 1;
        Modal.open(trashModal);
        loadTrash();
    }

    async function restoreRecord(id) {
        const ok = await confirmDialog({
            title: i18n.restore_title, text: i18n.restore_text,
            confirmText: i18n.restore_confirm, cancelText: i18n.cancel,
            icon: 'question', danger: false,
        });
        if (!ok) return;
        try {
            const { data } = await http.patch(routeFor(R.restore, id));
            toast('success', data.message);
            trashState.page = stepBack(trashContainer, trashState.page);
            await Promise.all([loadTrash(), loadRecords()]);
        } catch (err) { handleError(err); }
    }

    async function forceDeleteRecord(id) {
        const ok = await confirmDialog({
            title: i18n.force_title, text: i18n.force_text,
            confirmText: i18n.force_confirm, cancelText: i18n.cancel, icon: 'error',
        });
        if (!ok) return;
        try {
            const { data } = await http.delete(routeFor(R.forceDelete, id));
            toast('success', data.message);
            trashState.page = stepBack(trashContainer, trashState.page);
            await loadTrash();
        } catch (err) { handleError(err); }
    }

    $('#trashSearch')?.addEventListener('input', debounce((e) => {
        trashState.search = e.target.value.trim();
        trashState.page = 1;
        loadTrash();
    }, 400));

    /* ---------- تفويض الأحداث ---------- */
    app.addEventListener('click', (e) => {
        const actionEl = e.target.closest('[data-action]');
        if (actionEl) {
            const { action, id } = actionEl.dataset;
            switch (action) {
                case 'open-create': return openCreateModal();
                case 'open-trash': return openTrashModal();
                case 'show': return openShowModal(id);
                case 'edit': return openEditModal(id);
                case 'delete': return confirmDelete(id);
                case 'restore': return restoreRecord(id);
                case 'force-delete': return forceDeleteRecord(id);
                case 'reset-filters': return resetFilters();
                case 'clear-search': {
                    const s = app.querySelector('[data-filter="search"]');
                    if (s) s.value = '';
                    return filterRecords();
                }
            }
        }

        const sortTh = e.target.closest('th[data-sort]');
        if (sortTh) {
            const col = sortTh.dataset.sort;
            state.dir = state.sort === col && state.dir === 'desc' ? 'asc' : 'desc';
            state.sort = col;
            state.page = 1;
            return loadRecords();
        }

        const pageLink = e.target.closest('.crud-pagination a.page-link');
        if (pageLink) {
            e.preventDefault();
            if (trashContainer?.contains(pageLink)) {
                trashState.page = pageFromLink(pageLink);
                return loadTrash();
            }
            state.page = pageFromLink(pageLink);
            return loadRecords();
        }
    });

    createForm.addEventListener('submit', (e) => { e.preventDefault(); storeRecord(); });
    editForm.addEventListener('submit', (e) => { e.preventDefault(); updateRecord(); });

    hooks.init?.(app);

    return { loadRecords };
}