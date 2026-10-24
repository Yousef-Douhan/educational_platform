import axios from 'axios';
import Swal from 'sweetalert2';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

export const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    },
});

let messages = { errors: {} };
export const setMessages = (m) => { messages = m; };
export const isRtl = () => document.documentElement.dir === 'rtl';
export const routeFor = (tpl, id) => tpl.replace(':id', encodeURIComponent(id));

export function debounce(fn, wait = 400) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

/* ---------- Notifications ---------- */
export function toast(icon, title) {
    return Swal.fire({
        toast: true, position: isRtl() ? 'top-start' : 'top-end',
        icon, title, showConfirmButton: false, timer: 3200, timerProgressBar: true,
    });
}

export async function confirmDialog({ title, text, confirmText, cancelText, icon = 'warning', danger = true }) {
    const result = await Swal.fire({
        title, text, icon,
        showCancelButton: true, focusCancel: true,
        confirmButtonText: confirmText, cancelButtonText: cancelText,
        confirmButtonColor: danger ? '#ef4444' : '#6366f1',
        cancelButtonColor: '#64748b',
    });
    return result.isConfirmed;
}

/* ---------- Loading & validation ---------- */
export function setLoading(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.disabled = on;
}

export function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
}

export function showFieldErrors(form, errors) {
    Object.entries(errors).forEach(([field, msgs]) => {
        const message = Array.isArray(msgs) ? msgs[0] : msgs;
        const root = field.split('.')[0];

        const box = form.querySelector(`[data-error-for="${field}"]`) || form.querySelector(`[data-error-for="${root}"]`);
        if (box && !box.textContent) box.textContent = message;

        const bracket = field.replace(/\.([^.]+)/g, '[$1]');
        (form.querySelector(`[name="${bracket}"]`) || form.querySelector(`[name="${field}"]`))?.classList.add('is-invalid');
    });
    form.querySelector('.is-invalid')?.focus();
}

export function handleError(error, form = null) {
    console.error('CRUD ERROR:', error);
    const e = messages.errors || {};
    if (!error.response) return toast('error', e.network);

    const { status, data } = error.response;
    switch (status) {
        case 401: return toast('error', e.session_expired);
        case 403: return toast('error', e.forbidden);
        case 404: return toast('error', e.not_found);
        case 409: return toast('warning', data?.message || e.conflict);
        case 422:
            if (form && data?.errors) showFieldErrors(form, data.errors);
            return toast('error', e.validation);
        case 419: return toast('error', e.session_expired);
        case 500: return toast('error', e.server);
        default: return toast('error', e.unknown);
    }
}

/* ---------- Modals ---------- */
const openModals = [];
let lastFocus = null;

export const Modal = {
    open(el) {
        lastFocus = document.activeElement;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        openModals.push(el);
        setTimeout(() => el.querySelector('input:not([hidden]):not([type=hidden]), select, textarea, button')?.focus(), 60);
    },
    close(el) {
        if (!el) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        const i = openModals.indexOf(el);
        if (i > -1) openModals.splice(i, 1);
        if (!openModals.length) document.body.classList.remove('modal-open');
        lastFocus?.focus?.();
    },
};

/* ---------- Dropdowns (fixed positioning to avoid table clipping) ---------- */
function closeDropdowns() {
    document.querySelectorAll('.action-dropdown.is-open').forEach((d) => d.classList.remove('is-open'));
}

function toggleDropdown(toggle) {
    const dropdown = toggle.closest('.action-dropdown');
    const wasOpen = dropdown.classList.contains('is-open');
    closeDropdowns();
    if (wasOpen) return;

    const menu = dropdown.querySelector('.action-menu');
    dropdown.classList.add('is-open');

    const rect = toggle.getBoundingClientRect();
    const mh = menu.offsetHeight;
    const mw = menu.offsetWidth;
    const top = rect.bottom + mh + 8 > window.innerHeight ? rect.top - mh - 6 : rect.bottom + 6;
    menu.style.top = `${Math.max(8, top)}px`;
    const left = isRtl() ? rect.left : rect.right - mw;
    menu.style.left = `${Math.min(Math.max(8, left), window.innerWidth - mw - 8)}px`;
}

export function initCommon() {
    document.addEventListener('click', (e) => {
        const closer = e.target.closest('[data-modal-close]');
        if (closer) return Modal.close(closer.closest('.crud-modal'));

        if (e.target.classList?.contains('crud-modal')) return Modal.close(e.target);

        const toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) return toggleDropdown(toggle);
        closeDropdowns();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        closeDropdowns();
        if (!document.querySelector('.swal2-container')) Modal.close(openModals[openModals.length - 1]);
    });

    window.addEventListener('resize', closeDropdowns);
    window.addEventListener('scroll', closeDropdowns, true);
}