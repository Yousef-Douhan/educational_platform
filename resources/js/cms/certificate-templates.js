import { createCrudModule, setText, fmtDate } from './crud-module';
import { showFieldErrors } from './crud-common';

const app = document.getElementById('templatesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const MAX_BYTES = 5 * 1024 * 1024;

    function setPreview(target, url) {
        target.replaceChildren();
        if (url) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            target.append(img);
        } else {
            const icon = document.createElement('i');
            icon.className = 'fa-regular fa-image';
            target.append(icon);
        }
    }

    createCrudModule(app, {
        init() {
            ['#createForm', '#editForm'].forEach((sel) => {
                const form = app.querySelector(sel);
                const input = form.querySelector('[data-design-input]');

                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) return;
                    if (file.size > MAX_BYTES) {
                        input.value = '';
                        return showFieldErrors(form, { design: [i18n.design_too_large] });
                    }
                    setPreview(form.querySelector('[data-design-preview]'), URL.createObjectURL(file));
                });
            });
        },

        onCreate(form) {
            setPreview(form.querySelector('[data-design-preview]'), null);
        },

        onEdit(form, payload) {
            const t = payload.data;
            form.elements.name.value = t.name;
            form.elements.is_default.checked = Boolean(t.is_default);
            setPreview(form.querySelector('[data-design-preview]'), t.design_url);

            const L = t.layout || {};
            ['area_left', 'area_width', 'middle_top', 'qr_top'].forEach((k) => { form.elements[`layout[${k}]`].value = L[k] ?? ''; });
            ['hide_header', 'hide_date', 'hide_signature', 'hide_qr'].forEach((k) => { form.elements[`layout[${k}]`].checked = Boolean(L[k]); });
            form.elements['layout[text_color]'].value = L.text_color || '#111827';
            form.elements['layout[accent_color]'].value = L.accent_color || '#3730a3';
        },

        onShow(modal, payload) {
            const t = payload.data;
            setText(modal, 'name', t.name);
            setText(modal, 'certificates_count', t.certificates_count);
            setText(modal, 'created_at', fmtDate(t.created_at));
            setText(modal, 'updated_at', fmtDate(t.updated_at));
            setPreview(modal.querySelector('[data-show-design]'), t.design_url);

            const badge = modal.querySelector('[data-show-default]');
            badge.textContent = t.is_default ? i18n.yes_default : i18n.no_default;
            badge.className = `badge badge--${t.is_default ? 'success' : 'muted'}`;
        },
    });
}