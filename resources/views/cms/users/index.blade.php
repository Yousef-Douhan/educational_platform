@extends('cms.parent')

@section('title', __('users.title'))

@push('styles')
    {{-- <link rel="stylesheet" href="{{ asset('css/cms/users-ui.css') }}"> --}}
    <link rel="stylesheet" href="{{asset('css/cms/users-ui.css')}}">
    <style>
        [hidden] { display: none !important; }
        .uu-chip { display: inline-block; margin: 0 .25rem .25rem 0; padding: .15rem .55rem; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: .75rem; font-weight: 600; }
        .uu-muted { color: #98a2b3; }
        .uu-roles { display: flex; flex-wrap: wrap; gap: .5rem; }
        .uu-role-opt { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .7rem; border: 1px solid #d0d5dd; border-radius: 8px; font-size: .85rem; cursor: pointer; }
        .uu-role-opt:has(input:checked) { border-color: #6366f1; background: #eef2ff; }
        .uu-hint { margin: 0; font-size: .8rem; color: #667085; }
    </style>
@endpush

@section('main-content')
<div class="uu-page">

    {{-- ============ Header ============ --}}
    <header class="uu-head">
        <div>
            <h1 class="uu-title">{{ __('users.title') }}</h1>
            <p class="uu-sub">{{ __('users.ui.subtitle') }}</p>
        </div>
        <div class="uu-head-actions">
            @can('users.restore')
                <a href="{{ route('cms.admin.users.trashed') }}" class="uu-btn uu-btn--ghost">
                    <i class="fa-solid fa-trash-can-arrow-up" aria-hidden="true"></i> {{ __('users.ui.trash') }}
                </a>
            @endcan
            @can('users.create')
                <button type="button" class="uu-btn uu-btn--primary" onclick="openCreateModal()">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('users.ui.add_user') }}
                </button>
            @endcan
        </div>
    </header>

    {{-- ============ Stats ============ --}}
    <section class="uu-stats" aria-label="{{ __('users.ui.stats_aria') }}">
        <div class="uu-stat">
            <span class="uu-stat-label"><i class="uu-dot uu-dot--brand"></i> {{ __('users.ui.total') }}</span>
            <p class="uu-stat-value" id="stat-total">{{ $stats['total'] ?? 0 }}</p>
        </div>
        <div class="uu-stat">
            <span class="uu-stat-label"><i class="uu-dot uu-dot--ok"></i> {{ __('users.active') }}</span>
            <p class="uu-stat-value" id="stat-active">{{ $stats['active'] ?? 0 }}</p>
        </div>
        <div class="uu-stat">
            <span class="uu-stat-label"><i class="uu-dot uu-dot--warn"></i> {{ __('users.inactive') }}</span>
            <p class="uu-stat-value" id="stat-inactive">{{ $stats['inactive'] ?? 0 }}</p>
        </div>
        <div class="uu-stat">
            <span class="uu-stat-label"><i class="uu-dot uu-dot--bad"></i> {{ __('users.ui.in_trash') }}</span>
            <p class="uu-stat-value" id="stat-trashed">{{ $stats['trashed'] ?? 0 }}</p>
        </div>
    </section>

    {{-- ============ Table ============ --}}
    <section class="uu-panel" id="users-panel">
        <div class="uu-toolbar">
            <div class="uu-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="search-input" class="uu-input" placeholder="{{ __('users.ui.search_ph') }}" aria-label="{{ __('users.ui.search_aria') }}" autocomplete="off">
            </div>

            <input type="hidden" id="status-filter" value="">
            <div class="uu-tabs" role="group" aria-label="{{ __('users.ui.filter_aria') }}">
                <button type="button" class="uu-tab is-active" data-status="" aria-pressed="true">{{ __('users.ui.all') }}</button>
                <button type="button" class="uu-tab" data-status="active" aria-pressed="false">{{ __('users.active') }}</button>
                <button type="button" class="uu-tab" data-status="inactive" aria-pressed="false">{{ __('users.inactive') }}</button>
                <button type="button" class="uu-tab" data-status="suspended" aria-pressed="false">{{ __('users.suspended') }}</button>
            </div>
        </div>

        <div class="uu-table-wrap">
            <table class="uu-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('users.ui.user') }}</th>
                        <th scope="col">{{ __('users.roles') }}</th>
                        <th scope="col">{{ __('users.status') }}</th>
                        <th scope="col" class="uu-col-date">{{ __('users.ui.created') }}</th>
                        <th scope="col" class="uu-th-end"><span class="sr-only" style="position:absolute;left:-9999px">{{ __('users.ui.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody id="users-table-body"></tbody>
            </table>
        </div>

        <div class="uu-pager" id="pagination-wrapper"></div>
    </section>
</div>

{{-- ============ Drawers ============ --}}
@include('cms.users.create')
@include('cms.users.edit')
@include('cms.users.show')
@endsection

@push('scripts')
@php
    $can = [
        'create' => auth()->user()->can('users.create'),
        'edit'   => auth()->user()->can('users.edit'),
        'delete' => auth()->user()->can('users.delete'),
    ];
@endphp
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@php
    $uiStrings = array_merge(__('users.ui'), [
        'active'    => __('users.active'),
        'inactive'  => __('users.inactive'),
        'suspended' => __('users.suspended'),
    ]);
@endphp
<script>window.UU_T = @json($uiStrings);</script>
<script src="{{ asset('js/cms/users-ui.js') }}"></script>
<script>
    const $ = (id) => document.getElementById(id);
    const COLS = 5;
    const CAN = @json($can);
    const T = @json(__('users.ui'));
    const fmt = (s, n) => s.replace(':name', n);
    let requestId = 0;
    let firstLoad = true;

    /* ---------- Data ---------- */
    function loadUsers(page = 1) {
        const tbody  = $('users-table-body');
        const panel  = $('users-panel');
        const search = $('search-input').value.trim();
        const status = $('status-filter').value;
        const id = ++requestId;

        if (firstLoad) tbody.innerHTML = UU.skeletonRows(6, COLS);
        panel.classList.add('is-loading');

        axios.get('/cms/admin/users', { params: { page, search, status } })
            .then(({ data }) => {
                if (id !== requestId) return;            // ignore stale responses
                firstLoad = false;

                if (!data.data.length) {
                    const filtered = search || status;
                    tbody.innerHTML = UU.emptyRow(
                        COLS,
                        filtered ? 'fa-magnifying-glass' : 'fa-users',
                        filtered ? T.no_match : T.no_users,
                        filtered ? T.try_other : T.add_first,
                        filtered
                            ? '<button type="button" class="uu-btn uu-btn--ghost" onclick="clearFilters()">' + T.clear_filters + '</button>'
                            : (CAN.create ? '<button type="button" class="uu-btn uu-btn--primary" onclick="openCreateModal()"><i class="fa-solid fa-plus"></i> ' + T.add_user + '</button>' : '')
                    );
                    $('pagination-wrapper').innerHTML = '';
                    return;
                }

                tbody.innerHTML = data.data.map(rowHtml).join('');
                UU.renderPagination(data, loadUsers);
            })
            .catch(() => {
                if (id !== requestId) return;
                firstLoad = false;
                tbody.innerHTML = UU.emptyRow(COLS, 'fa-triangle-exclamation', T.load_fail,
                    T.check_conn,
                    '<button type="button" class="uu-btn uu-btn--ghost" onclick="loadUsers()">' + T.try_again + '</button>');
            })
            .finally(() => { if (id === requestId) panel.classList.remove('is-loading'); });
    }

    function rolesHtml(roles) {
        return (roles || []).map((r) => `<span class="uu-chip">${UU.esc(r.name)}</span>`).join('')
            || '<span class="uu-muted">—</span>';
    }

    function rowHtml(u) {
        const name = UU.esc(u.name);
        const canEdit = CAN.edit && !u.is_protected;
        const canDelete = CAN.delete && !u.is_protected && !u.is_self;
        return `
        <tr>
            <td>
                <div class="uu-user">
                    ${UU.avatar(u.name)}
                    <div class="uu-user-text">
                        <div class="uu-user-name">${name}</div>
                        <div class="uu-user-mail">${UU.esc(u.email)}</div>
                    </div>
                </div>
            </td>
            <td>${rolesHtml(u.roles)}</td>
            <td>${UU.badge(u.status)}</td>
            <td class="uu-col-date">${UU.fmtDate(u.created_at)}</td>
            <td class="uu-td-end">
                <div class="uu-actions">
                    <button type="button" class="uu-icon-btn" data-action="view" data-id="${u.id}" aria-label="${fmt(T.view_x, name)}" title="${T.view}"><i class="fa-solid fa-eye"></i></button>
                    ${canEdit ? `<button type="button" class="uu-icon-btn" data-action="edit" data-id="${u.id}" aria-label="${fmt(T.edit_x, name)}" title="${T.edit}"><i class="fa-solid fa-pen-to-square"></i></button>` : ''}
                    ${canDelete ? `<button type="button" class="uu-icon-btn uu-icon-btn--danger" data-action="delete" data-id="${u.id}" data-name="${name}" aria-label="${fmt(T.trash_x, name)}" title="${T.move_trash}"><i class="fa-solid fa-trash-can"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }

    function clearFilters() {
        $('search-input').value = '';
        setStatus('');
        loadUsers(1);
    }

    function setStatus(value) {
        $('status-filter').value = value;
        document.querySelectorAll('.uu-tab').forEach((t) => {
            const on = t.dataset.status === value;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-pressed', on);
        });
    }

    /* ---------- Drawers ---------- */
    function openCreateModal() {
        const form = $('createUserForm');
        form.reset();
        UU.clearErrors(form);
        UU.openDrawer('createUserModal');
    }
    function closeCreateModal() { UU.closeDrawer('createUserModal'); }

    function openEditModal(id) {
        axios.get(`/cms/admin/users/${id}/edit`).then(({ data: user }) => {
            const form = $('editUserForm');
            form.reset();
            UU.clearErrors(form);
            $('edit-user-id').value = user.id;
            $('edit-name').value = user.name;
            $('edit-email').value = user.email;
            const radio = form.querySelector(`input[name="status"][value="${user.status}"]`);
            if (radio) radio.checked = true;
            const roleIds = (user.roles || []).map((r) => String(r.id));
            form.querySelectorAll('input[name="roles[]"]').forEach((cb) => { cb.checked = roleIds.includes(cb.value); });
            UU.openDrawer('editUserModal');
        }).catch((err) => UU.showToast(err.response?.data?.message || T.cant_load, 'error'));
    }
    function closeEditModal() { UU.closeDrawer('editUserModal'); }

    function openShowModal(id) {
        axios.get(`/cms/admin/users/${id}`).then(({ data: user }) => {
            $('show-avatar').innerHTML = UU.avatar(user.name, true);
            $('show-name').textContent = user.name;
            $('show-email').textContent = user.email;
            $('show-status').innerHTML = UU.badge(user.status);
            // $('show-status-row').innerHTML = UU.badge(user.status);
            $('show-created').textContent = UU.fmtDateTime(user.created_at);
            $('show-roles').innerHTML = rolesHtml(user.roles);
            $('show-edit-btn').dataset.id = user.id;
            $('show-edit-btn').hidden = !CAN.edit;
            UU.openDrawer('showUserModal');
        }).catch((err) => UU.showToast(err.response?.data?.message || T.cant_load, 'error'));
    }
    function closeShowModal() { UU.closeDrawer('showUserModal'); }

    /* ---------- Delete ---------- */
    function confirmDelete(id, name) {
        Swal.fire({
            title: T.trash_q,
            html: fmt(T.trash_text, `<strong>${UU.esc(name)}</strong>`),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d92d20',
            cancelButtonColor: '#667085',
            confirmButtonText: T.move_trash,
            cancelButtonText: T.cancel,
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (!result.isConfirmed) return;
            axios.delete(`/cms/admin/users/${id}`).then(({ data }) => {
                if (data.success) {
                    loadUsers();
                    UU.showToast(data.message, 'success');
                }
            }).catch((err) => UU.showToast(err.response?.data?.message || T.cant_trash, 'error'));
        });
    }

    /* ---------- Errors ---------- */
    function fail(err, formId) {
        const status = err.response?.status;
        if (status === 401 || status === 419) return window.location.reload();
        if (status === 403 || status === 409) {
            return UU.showToast(err.response.data?.message || T.not_allowed, 'error');
        }
        UU.handleFormErrors(err, formId);
    }

    /* ---------- Wiring ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        loadUsers();

        $('search-input').addEventListener('input', UU.debounce(() => loadUsers(1), 350));

        document.querySelectorAll('.uu-tab').forEach((tab) => {
            tab.addEventListener('click', () => { setStatus(tab.dataset.status); loadUsers(1); });
        });

        // Row actions (event delegation — no inline handlers, no injection)
        $('users-table-body').addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const id = btn.dataset.id;
            if (btn.dataset.action === 'view') openShowModal(id);
            if (btn.dataset.action === 'edit') openEditModal(id);
            if (btn.dataset.action === 'delete') confirmDelete(id, btn.dataset.name);
        });

        // "Edit user" shortcut inside the details drawer
        $('show-edit-btn').addEventListener('click', (e) => openEditModal(e.currentTarget.dataset.id));

        // Create
        $('createUserForm').addEventListener('submit', function (e) {
            e.preventDefault();
            UU.clearErrors(this);
            UU.setBusy(this, true);
            axios.post('/cms/admin/users', new FormData(this))
                .then(({ data }) => {
                    if (data.success) {
                        closeCreateModal();
                        this.reset();
                        loadUsers();
                        UU.showToast(data.message, 'success');
                    }
                })
                .catch((err) => fail(err, 'createUserForm'))
                .finally(() => UU.setBusy(this, false));
        });

        // Update
        $('editUserForm').addEventListener('submit', function (e) {
            e.preventDefault();
            UU.clearErrors(this);
            UU.setBusy(this, true);
            const id = $('edit-user-id').value;
            const fd = new FormData(this);
            fd.append('_method', 'PUT');
            axios.post(`/cms/admin/users/${id}`, fd)
                .then(({ data }) => {
                    if (data.success) {
                        closeEditModal();
                        loadUsers();
                        UU.showToast(data.message, 'success');
                    }
                })
                .catch((err) => fail(err, 'editUserForm'))
                .finally(() => UU.setBusy(this, false));
        });
    });
</script>
@endpush
