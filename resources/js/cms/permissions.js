import { createCrudModule, setText, fmtDate } from './crud-module';

const app = document.getElementById('permissionsApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);

    createCrudModule(app, {
        onCreate(form, data) {
            form.querySelector('[data-guard-select]').replaceChildren(...data.guards.map((g) => new Option(g, g)));
        },

        onEdit(form, payload) {
            form.querySelector('[name="name"]').value = payload.data.name;
            form.querySelector('[data-guard-display]').value = payload.data.guard_name;
        },

        onShow(modal, payload) {
            const p = payload.data;
            setText(modal, 'name', p.name);
            setText(modal, 'guard_name', p.guard_name);
            setText(modal, 'created_at', fmtDate(p.created_at));
            setText(modal, 'updated_at', fmtDate(p.updated_at));

            const box = modal.querySelector('[data-show-roles]');
            box.replaceChildren();
            if (!payload.roles.length) {
                const empty = document.createElement('div');
                empty.className = 'perm-empty';
                empty.textContent = i18n.no_roles;
                box.append(empty);
                return;
            }
            payload.roles.forEach((r) => {
                const chip = document.createElement('span');
                chip.className = 'perm-chip';
                chip.textContent = r.name;
                box.append(chip);
            });
        },
    });
}