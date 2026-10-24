import { createCrudModule, setText, fmtDate } from './crud-module';

const app = document.getElementById('categoriesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const DEFAULT_ICON = 'fa-solid fa-folder';
    const safeIcon = (v) => (v && /^[A-Za-z0-9\- ]+$/.test(v.trim()) ? v.trim() : DEFAULT_ICON);

    function setPreview(form, value) {
        const icon = form.querySelector('[data-icon-preview] i');
        icon.className = safeIcon(value);
    }

    function fillParents(select, parents, selected = null) {
        select.replaceChildren(new Option(i18n.root_option, ''));
        parents.forEach((p) => select.append(new Option(`${p.name_ar} — ${p.name_en}`, p.id, false, Number(selected) === p.id)));
    }

    createCrudModule(app, {
        init() {
            ['#createForm', '#editForm'].forEach((sel) => {
                const form = app.querySelector(sel);
                form.querySelector('[data-icon-input]').addEventListener('input', (e) => setPreview(form, e.target.value));
            });
        },

        onCreate(form, data) {
            fillParents(form.querySelector('[data-parent-select]'), data.parents);
            setPreview(form, '');
        },

        onEdit(form, payload) {
            const c = payload.data;
            ['name_ar', 'name_en', 'slug', 'icon'].forEach((f) => { form.elements[f].value = c[f] ?? ''; });
            fillParents(form.querySelector('[data-parent-select]'), payload.parents, c.parent_id);
            setPreview(form, c.icon);
        },

        onShow(modal, payload) {
            const c = payload.data;
            setText(modal, 'name_ar', c.name_ar);
            setText(modal, 'name_en', c.name_en);
            setText(modal, 'slug', c.slug);
            setText(modal, 'parent_name', c.parent ? `${c.parent.name_ar} — ${c.parent.name_en}` : i18n.root_label);
            setText(modal, 'children_count', c.children_count);
            setText(modal, 'created_at', fmtDate(c.created_at));
            setText(modal, 'updated_at', fmtDate(c.updated_at));
            modal.querySelector('[data-show-icon]').className = safeIcon(c.icon);

            const box = modal.querySelector('[data-show-children]');
            box.replaceChildren();
            if (!payload.children.length) {
                const empty = document.createElement('span');
                empty.className = 'cat-empty';
                empty.textContent = i18n.no_children;
                box.append(empty);
                return;
            }
            payload.children.forEach((ch) => {
                const chip = document.createElement('span');
                chip.className = 'cat-chip';
                const i = document.createElement('i');
                i.className = safeIcon(ch.icon);
                chip.append(i, document.createTextNode(` ${ch.name_ar} — ${ch.name_en}`));
                box.append(chip);
            });
        },
    });
}