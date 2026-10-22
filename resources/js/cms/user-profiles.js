import {
    http, toast, confirmDialog, Modal, setLoading, setMessages, initCommon,
    clearFieldErrors, showFieldErrors, handleError, debounce, routeFor,
} from './crud-common';

const app = document.getElementById('userProfilesApp');

if (app) {
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
    const trashContainer = $('#trashContainer');
    const trashCard = $('#trashCard');
    const createModal = $('#createModal');
    const editModal = $('#editModal');
    const showModal = $('#showModal');
    const trashModal = $('#trashModal');
    const createForm = $('#createForm');
    const editForm = $('#editForm');
    const filterSearch = $('#filterSearch');
    const clearSearchBtn = $('#clearSearch');

    const state = { search: '', user_id: '', from: '', to: '', sort: 'id', dir: 'desc', page: 1 };
    const trashState = { search: '', page: 1 };

    const cleanParams = (obj) => Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v != null));

    /* ---------- Stats ---------- */
    function updateStats(stats) {
        if (!stats) return;
        Object.entries(stats).forEach(([key, value]) => {
            const el = app.querySelector(`[data-stat="${key}"]`);
            if (el) el.textContent = value;
        });
    }

    /* ---------- Loading list ---------- */
    let reqId = 0;
    async function loadRecords() {
        const id = ++reqId;
        tableCard.classList.add('is-loading');
        try {
            const { data } = await http.get(R.index, { params: cleanParams(state) });
            if (id !== reqId) return;
            tableContainer.innerHTML = data.html;
            updateStats(data.stats);
        } catch (err) {
            handleError(err);
        } finally {
            if (id === reqId) tableCard.classList.remove('is-loading');
        }
    }

    let trashReq = 0;
    async function loadTrash() {
        const id = ++trashReq;
        trashCard.classList.add('is-loading');
        try {
            const { data } = await http.get(R.trashed, { params: cleanParams(trashState) });
            if (id !== trashReq) return;
            trashContainer.innerHTML = data.html;
            updateStats(data.stats);
        } catch (err) {
            handleError(err);
        } finally {
            if (id === trashReq) trashCard.classList.remove('is-loading');
        }
    }

    const pageFromLink = (a) => Number(new URL(a.href).searchParams.get('page')) || 1;

    function stepBackIfLastRow(container, current) {
        return container.querySelectorAll('tr[data-id]').length === 1 && current > 1 ? current - 1 : current;
    }

    /* ---------- Filters ---------- */
    function filterRecords() {
        state.search = filterSearch.value.trim();
        state.user_id = $('#filterUser').value;
        state.from = $('#filterFrom').value;
        state.to = $('#filterTo').value;
        state.page = 1;
        clearSearchBtn.hidden = !state.search;
        loadRecords();
    }

    function resetFilters() {
        filterSearch.value = '';
        $('#filterUser').value = '';
        $('#filterFrom').value = '';
        $('#filterTo').value = '';
        filterRecords();
    }

    filterSearch.addEventListener('input', debounce(filterRecords, 400));
    ['#filterUser', '#filterFrom', '#filterTo'].forEach((s) => $(s).addEventListener('change', filterRecords));
    $('#trashSearch').addEventListener('input', debounce((e) => {
        trashState.search = e.target.value.trim();
        trashState.page = 1;
        loadTrash();
    }, 400));

    /* ---------- Avatar helpers ---------- */
    function setPreview(form, url) {
        const box = form.querySelector('[data-avatar-preview]');
        box.replaceChildren();
        box.classList.toggle('has-image', Boolean(url));
        if (url) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            box.append(img);
        } else {
            const icon = document.createElement('i');
            icon.className = 'fa-regular fa-image';
            box.append(icon);
        }
    }

    [createForm, editForm].forEach((form) => {
        const input = form.querySelector('[data-avatar-input]');
        input.addEventListener('change', () => {
            const file = input.files[0];
            clearFieldErrors(form);
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                input.value = '';
                return showFieldErrors(form, { avatar: [i18n.avatar_too_large] });
            }
            setPreview(form, URL.createObjectURL(file));
        });
    });

    editForm.querySelector('[data-remove-avatar]').addEventListener('change', (e) => {
        if (e.target.checked) {
            editForm.querySelector('[data-avatar-input]').value = '';
            setPreview(editForm, null);
        }
    });

    function fillSelect(select, users, selected = null) {
        select.replaceChildren(new Option(i18n.select_user, ''));
        users.forEach((u) => select.append(new Option(`${u.name} — ${u.email}`, u.id, false, Number(selected) === u.id)));
    }

    /* ---------- Create ---------- */
    async function openCreateModal() {
        try {
            const { data } = await http.get(R.create);
            if (!data.users.length) return toast('info', i18n.no_available_users);
            createForm.reset();
            clearFieldErrors(createForm);
            setPreview(createForm, null);
            fillSelect(createForm.elements.user_id, data.users);
            Modal.open(createModal);
        } catch (err) { handleError(err); }
    }

    async function storeRecord() {
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

    /* ---------- Edit ---------- */
    async function openEditModal(id) {
        try {
            const { data } = await http.get(routeFor(R.edit, id));
            const p = data.data;
            editForm.reset();
            clearFieldErrors(editForm);
            editForm.dataset.id = p.id;
            fillSelect(editForm.elements.user_id, data.users, p.user_id);
            ['phone', 'website', 'social_links', 'bio'].forEach((f) => { editForm.elements[f].value = p[f] ?? ''; });
            setPreview(editForm, p.avatar_url);
            editForm.querySelector('[data-remove-avatar-wrap]').hidden = !p.avatar;
            Modal.open(editModal);
        } catch (err) { handleError(err); }
    }

    async function updateRecord() {
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

    /* ---------- Show ---------- */
    const fmtDate = (iso) => (iso ? new Date(iso).toLocaleString(document.documentElement.lang || undefined) : '—');

    async function openShowModal(id) {
        try {
            const { data } = await http.get(routeFor(R.show, id));
            const p = data.data;
            const set = (key, value) => {
                const el = showModal.querySelector(`[data-show="${key}"]`);
                if (el) el.textContent = value || '—';
            };
            set('name', p.user?.name);
            set('email', p.user?.email);
            set('phone', p.phone);
            set('social_links', p.social_links);
            set('bio', p.bio);
            set('created_at', fmtDate(p.created_at));
            set('updated_at', fmtDate(p.updated_at));

            const link = showModal.querySelector('a[data-show="website"]');
            link.textContent = p.website || '—';
            if (p.website && /^https?:\/\//i.test(p.website)) link.href = p.website;
            else link.removeAttribute('href');

            const avatar = showModal.querySelector('[data-show-avatar]');
            avatar.replaceChildren();
            avatar.classList.toggle('avatar--initial', !p.avatar_url);
            if (p.avatar_url) {
                const img = document.createElement('img');
                img.src = p.avatar_url;
                img.alt = '';
                avatar.append(img);
            } else {
                avatar.textContent = (p.user?.name || '?').charAt(0).toUpperCase();
            }
            Modal.open(showModal);
        } catch (err) { handleError(err); }
    }

    /* ---------- Delete / Trash ---------- */
    async function confirmDelete(id) {
        const ok = await confirmDialog({
            title: i18n.delete_title, text: i18n.delete_text,
            confirmText: i18n.delete_confirm, cancelText: i18n.cancel,
        });
        if (ok) deleteRecord(id);
    }

    async function deleteRecord(id) {
        try {
            const { data } = await http.delete(routeFor(R.destroy, id));
            toast('success', data.message);
            state.page = stepBackIfLastRow(tableContainer, state.page);
            await loadRecords();
        } catch (err) { handleError(err); }
    }

    function openTrashModal() {
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
            trashState.page = stepBackIfLastRow(trashContainer, trashState.page);
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
            trashState.page = stepBackIfLastRow(trashContainer, trashState.page);
            await loadTrash();
        } catch (err) { handleError(err); }
    }

    /* ---------- Event delegation ---------- */
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
                case 'clear-search': filterSearch.value = ''; return filterRecords();
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
            if (trashContainer.contains(pageLink)) {
                trashState.page = pageFromLink(pageLink);
                return loadTrash();
            }
            state.page = pageFromLink(pageLink);
            return loadRecords();
        }
    });

    createForm.addEventListener('submit', (e) => { e.preventDefault(); storeRecord(); });
    editForm.addEventListener('submit', (e) => { e.preventDefault(); updateRecord(); });
}