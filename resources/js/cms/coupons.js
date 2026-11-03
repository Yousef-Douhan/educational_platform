import { createCrudModule, setText } from './crud-module';
import { http, toast, handleError, routeFor } from './crud-common';

const app = document.getElementById('couponsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const toggleUrl = app.dataset.toggleUrl;
    const LOCKED_INPUTS = ['code', 'discount_value'];
    // بعد إضافة الحقل المخفي يتكرر الاسم، فلا نعتمد على form.elements لهذا الحقل
    const typeSelect = (form) => form.querySelector('select[name="discount_type"]');
    // بلا 0/O/1/I/L حتى لا يلتبس الكود عند كتابته يدويًا
    const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v);
    const num = (v) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

    function randomCode() {
        const pick = (n) => Array.from(crypto.getRandomValues(new Uint32Array(n)), (x) => ALPHABET[x % ALPHABET.length]).join('');
        return `SAVE-${pick(4)}-${pick(4)}`;
    }

    /** يظهر السقف للنسبة المئوية فقط، ويتغير الحد الأعلى والوحدة حسب النوع */
    function syncType(form) {
        const type = typeSelect(form).value;
        const isPct = type === 'percentage';
        const value = form.elements.discount_value;

        form.querySelector('[data-percentage-only]').hidden = !isPct;
        form.querySelector('[data-unit-badge]').textContent = isPct ? '%' : i18n.currency_unit;
        value.max = isPct ? '100' : '99999999.99';
        if (!isPct) form.elements.max_discount.value = '';
    }

    function setLocked(form, locked) {
        form.querySelector('input[name="discount_type"][type="hidden"]')?.remove();
        LOCKED_INPUTS.forEach((name) => { form.elements[name].readOnly = locked; });

        // select لا يدعم readOnly، فيُعطَّل ويُرسل قيمته عبر حقل مخفي
        const sel = typeSelect(form);
        sel.disabled = locked;
        if (locked) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'discount_type';
            hidden.value = sel.value;
            form.append(hidden);
        }

        form.querySelector('[data-locked-note]').hidden = !locked;
        form.querySelector('[data-action="generate-code"]').disabled = locked;
    }

    const crud = createCrudModule(app, {
        init() {
            ['#createForm', '#editForm'].forEach((sel) => {
                const form = app.querySelector(sel);
                typeSelect(form).addEventListener('change', () => syncType(form));
                // الكود بأحرف كبيرة أثناء الكتابة (الخادم يطبّع أيضًا)
                form.elements.code.addEventListener('input', (e) => {
                    const { selectionStart, selectionEnd } = e.target;
                    e.target.value = e.target.value.toUpperCase().replace(/\s+/g, '');
                    e.target.setSelectionRange(selectionStart, selectionEnd);
                });
            });
        },

        onCreate(form) {
            setLocked(form, false);
            form.elements.is_active.checked = true;
            syncType(form);
        },

        onEdit(form, payload) {
            const c = payload.data;
            setLocked(form, false);
            form.elements.code.value = c.code;
            typeSelect(form).value = c.discount_type;
            form.elements.discount_value.value = c.discount_value;
            form.elements.max_discount.value = c.max_discount ?? '';
            form.elements.min_order_amount.value = c.min_order_amount ?? '';
            form.elements.starts_at.value = c.starts_at_input ?? '';
            form.elements.expires_at.value = c.expires_at_input ?? '';
            form.elements.usage_limit.value = c.usage_limit ?? '';
            form.elements.per_user_limit.value = c.per_user_limit ?? '';
            form.elements.is_active.checked = Boolean(c.is_active);
            syncType(form);
            if (c.has_been_used) setLocked(form, true);
        },

        onShow(modal, payload) {
            const c = payload.data;
            const isPct = c.discount_type === 'percentage';
            setText(modal, 'code', c.code);
            setText(modal, 'type_label', i18n.types[c.discount_type]);
            setText(modal, 'value_label', isPct ? `${Number(c.discount_value)}%` : num(c.discount_value));
            setText(modal, 'max_discount', isPct ? dash(c.max_discount && num(c.max_discount)) : '—');
            setText(modal, 'min_order_amount', c.min_order_amount === null ? i18n.none : num(c.min_order_amount));
            setText(modal, 'usage_label', `${c.times_used} / ${c.usage_limit ?? '∞'}`);
            setText(modal, 'per_user_limit', c.per_user_limit ?? i18n.unlimited);
            setText(modal, 'starts_at_label', c.starts_at_label ?? i18n.immediately);
            setText(modal, 'expires_at_label', c.expires_at_label ?? i18n.no_expiry);
            setText(modal, 'created_at_label', c.created_at_label);
            setText(modal, 'updated_at_label', c.updated_at_label);

            const tone = { active: 'success', scheduled: 'primary', expired: 'danger', exhausted: 'info', disabled: 'muted' };
            const badge = modal.querySelector('[data-show-status]');
            badge.textContent = i18n.statuses[c.status];
            badge.className = `badge badge--${tone[c.status]}`;
        },

        async onAction(action, id, el) {
            if (action === 'generate-code') {
                const form = el.closest('form');
                form.elements.code.value = randomCode();
                form.elements.code.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            if (action === 'toggle') {
                try {
                    const { data } = await http.patch(routeFor(toggleUrl, id));
                    toast('success', data.message);
                    await crud.loadRecords();
                } catch (err) { handleError(err); }
            }
        },
    });
}
