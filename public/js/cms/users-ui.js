/* ==========================================================================
   Users UI — shared helpers (load AFTER axios and sweetalert2)
   ========================================================================== */
(function (w, d) {
    'use strict';

    /* ---------- Axios defaults ---------- */
    if (w.axios) {
        const csrf = d.querySelector('meta[name="csrf-token"]');
        w.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        w.axios.defaults.headers.common['Accept'] = 'application/json';
        if (csrf) w.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;
    }

    /* ---------- Formatting ---------- */
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const fmtDate = (v) => v ? new Date(v).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
    const fmtDateTime = (v) => v ? new Date(v).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/);
        const first = parts[0] ? Array.from(parts[0])[0] : '?';
        const last = parts.length > 1 ? Array.from(parts[parts.length - 1])[0] : '';
        return (first + last).toUpperCase();
    }

    function tone(name) {
        let h = 0;
        for (const c of String(name || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
        return h % 6;
    }

    const avatar = (name, large = false) =>
        `<span class="uu-avatar uu-tone-${tone(name)}${large ? ' uu-avatar--lg' : ''}" aria-hidden="true">${esc(initials(name))}</span>`;

    const STATUS = {
        active: { label: 'Active', cls: 'ok' },
        inactive: { label: 'Inactive', cls: 'warn' },
        suspended: { label: 'Suspended', cls: 'bad' },
    };
    const badge = (status) => {
        const m = STATUS[status] || { label: status || 'Unknown', cls: 'muted' };
        return `<span class="uu-badge uu-badge--${m.cls}">${esc(m.label)}</span>`;
    };

    /* ---------- Toast ---------- */
    function showToast(message, icon = 'success') {
        if (!w.Swal) return;
        w.Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false,
            timer: 3000, timerProgressBar: true,
        }).fire({ icon, title: message });
    }

    /* ---------- Utilities ---------- */
    function debounce(fn, wait) {
        let t;
        return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
    }

    function skeletonRows(rows, cols) {
        let html = '';
        for (let r = 0; r < rows; r++) {
            html += '<tr>';
            for (let c = 0; c < cols; c++) {
                if (c === 0) {
                    html += `<td><div class="uu-user"><span class="uu-skel uu-skel--circle"></span><div style="flex:1"><span class="uu-skel" style="width:55%;margin-bottom:8px"></span><span class="uu-skel" style="width:75%;height:10px"></span></div></div></td>`;
                } else {
                    html += `<td><span class="uu-skel" style="width:${50 + ((r + c) * 13) % 40}%"></span></td>`;
                }
            }
            html += '</tr>';
        }
        return html;
    }

    function emptyRow(cols, icon, title, text, actionHtml = '') {
        return `<tr><td colspan="${cols}"><div class="uu-empty">
            <span class="uu-empty-icon"><i class="fa-solid ${icon}"></i></span>
            <p class="uu-empty-title">${esc(title)}</p>
            <p class="uu-empty-text">${esc(text)}</p>
            ${actionHtml}
        </div></td></tr>`;
    }

    /* ---------- Pagination ---------- */
    function renderPagination(data, callback, wrapperId = 'pagination-wrapper') {
        const wrapper = d.getElementById(wrapperId);
        if (!wrapper) return;
        const go = typeof callback === 'function' ? callback : w[callback];

        if (!data.total) { wrapper.innerHTML = ''; return; }

        let html = `<span class="uu-pager-info">Showing ${data.from || 0}–${data.to || 0} of ${data.total}</span>`;

        if ((data.links || []).length > 3) {
            html += '<ul class="uu-pager-list">';
            const last = data.links.length - 1;
            data.links.forEach((link, i) => {
                const page = link.url ? new URL(link.url).searchParams.get('page') : null;
                const cls = 'uu-page-btn' + (link.active ? ' is-active' : '') + (!link.url ? ' is-disabled' : '');
                let label;
                if (i === 0) label = '<i class="fa-solid fa-chevron-left uu-flip" aria-hidden="true"></i><span class="sr-only" style="position:absolute;left:-9999px">Previous</span>';
                else if (i === last) label = '<i class="fa-solid fa-chevron-right uu-flip" aria-hidden="true"></i><span class="sr-only" style="position:absolute;left:-9999px">Next</span>';
                else label = esc(String(link.label).replace(/<[^>]*>/g, ''));
                html += `<li><a href="#" class="${cls}" ${page ? `data-page="${esc(page)}"` : 'aria-disabled="true" tabindex="-1"'} ${link.active ? 'aria-current="page"' : ''}>${label}</a></li>`;
            });
            html += '</ul>';
        }

        wrapper.innerHTML = html;
        wrapper.querySelectorAll('[data-page]').forEach((a) => {
            a.addEventListener('click', (e) => { e.preventDefault(); if (go) go(parseInt(a.dataset.page, 10)); });
        });
    }

    /* ---------- Forms ---------- */
    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.uu-error').forEach((el) => { el.textContent = ''; });
    }

    function handleFormErrors(error, formId) {
        const form = d.getElementById(formId);
        if (!form) return;
        clearErrors(form);

        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {};
            let first = null;
            Object.keys(errors).forEach((field) => {
                const input = form.querySelector(`[name="${field}"]`);
                const fb = form.querySelector(`.uu-error[data-field="${field}"]`);
                if (input && input.type !== 'radio') { input.classList.add('is-invalid'); first = first || input; }
                if (fb) fb.textContent = errors[field][0];
            });
            if (first) first.focus();
        } else {
            showToast('Something went wrong. Please try again.', 'error');
        }
    }

    function setBusy(form, busy) {
        const btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('is-loading', busy);
    }

    /* ---------- Drawers ---------- */
    let lastFocus = null;

    function openDrawer(id) {
        const prev = d.activeElement;
        closeAllDrawers();
        const el = d.getElementById(id);
        if (!el) return;
        lastFocus = prev;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        d.documentElement.classList.add('uu-lock');
        setTimeout(() => {
            const target = el.querySelector('[data-autofocus]') || el.querySelector('button, input, select');
            if (target) target.focus();
        }, 80);
    }

    function closeDrawer(id) {
        const el = d.getElementById(id);
        if (!el || !el.classList.contains('is-open')) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        if (!d.querySelector('.uu-drawer.is-open')) d.documentElement.classList.remove('uu-lock');
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    function closeAllDrawers() {
        d.querySelectorAll('.uu-drawer.is-open').forEach((el) => closeDrawer(el.id));
    }

    d.addEventListener('click', (e) => {
        if (e.target.closest('[data-drawer-close]')) { closeAllDrawers(); return; }

        const toggle = e.target.closest('[data-toggle-password]');
        if (toggle) {
            const input = toggle.closest('.uu-pw').querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        }
    });

    d.addEventListener('keydown', (e) => {
        const open = d.querySelector('.uu-drawer.is-open');
        if (!open) return;
        if (e.key === 'Escape') { closeAllDrawers(); return; }
        if (e.key === 'Tab') {
            const f = open.querySelectorAll('button:not([disabled]), input:not([type="hidden"]), select, a[href]');
            if (!f.length) return;
            const first = f[0], last = f[f.length - 1];
            if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    /* ---------- Public API (+ legacy globals) ---------- */
    w.UU = {
        esc, avatar, badge, fmtDate, fmtDateTime, showToast, debounce,
        skeletonRows, emptyRow, renderPagination,
        clearErrors, handleFormErrors, setBusy,
        openDrawer, closeDrawer, closeAllDrawers,
    };
    w.showToast = showToast;
    w.debounce = debounce;
    w.renderPagination = renderPagination;
    w.handleFormErrors = handleFormErrors;
})(window, document);
