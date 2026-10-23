import { createCrudModule, setText, fmtDate } from './crud-module';

const app = document.getElementById('rolesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    let createGroups = [];

    function renderGroups(container, groups, selected = [], guard = null) {
        container.replaceChildren();
        let count = 0;

        groups.forEach(({ group, items }) => {
            const list = guard ? items.filter((p) => p.guard_name === guard) : items;
            if (!list.length) return;
            count += list.length;

            const box = document.createElement('div');
            box.className = 'perm-group';

            const head = document.createElement('label');
            head.className = 'perm-group__head';
            const all = document.createElement('input');
            all.type = 'checkbox';
            all.dataset.groupAll = '';
            all.checked = list.every((p) => selected.includes(p.id));
            const title = document.createElement('span');
            title.textContent = group;
            head.append(all, title);

            const grid = document.createElement('div');
            grid.className = 'perm-group__grid';
            list.forEach((p) => {
                const label = document.createElement('label');
                label.className = 'perm-item';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'permissions[]';
                cb.value = p.id;
                cb.checked = selected.includes(p.id);
                const name = document.createElement('span');
                name.textContent = p.name;
                label.append(cb, name);
                grid.append(label);
            });

            box.append(head, grid);
            container.append(box);
        });

        if (!count) {
            const empty = document.createElement('div');
            empty.className = 'perm-empty';
            empty.textContent = i18n.no_permissions;
            container.append(empty);
        }
    }

    function attachGroupEvents(container) {
        container.addEventListener('change', (e) => {
            const box = e.target.closest('.perm-group');
            if (!box) return;
            const items = [...box.querySelectorAll('input[name="permissions[]"]')];
            const all = box.querySelector('[data-group-all]');
            if (e.target === all) items.forEach((cb) => { cb.checked = all.checked; });
            else all.checked = items.every((cb) => cb.checked);
        });
    }

    createCrudModule(app, {
        init() {
            app.querySelectorAll('[data-permission-groups]').forEach(attachGroupEvents);
            const form = app.querySelector('#createForm');
            form.querySelector('[data-guard-select]').addEventListener('change', (e) => {
                renderGroups(form.querySelector('[data-permission-groups]'), createGroups, [], e.target.value);
            });
        },

        onCreate(form, data) {
            const select = form.querySelector('[data-guard-select]');
            select.replaceChildren(...data.guards.map((g) => new Option(g, g)));
            createGroups = data.groups;
            renderGroups(form.querySelector('[data-permission-groups]'), createGroups, [], select.value);
        },

        onEdit(form, payload) {
            form.querySelector('[name="name"]').value = payload.data.name;
            form.querySelector('[data-guard-display]').value = payload.data.guard_name;
            renderGroups(form.querySelector('[data-permission-groups]'), payload.groups, payload.permission_ids);
        },

        onShow(modal, payload) {
            const r = payload.data;
            setText(modal, 'name', r.name);
            setText(modal, 'guard_name', r.guard_name);
            setText(modal, 'users_count', r.users_count);
            setText(modal, 'permissions_count', r.permissions.length);
            setText(modal, 'created_at', fmtDate(r.created_at));
            setText(modal, 'updated_at', fmtDate(r.updated_at));

            const box = modal.querySelector('[data-show-groups]');
            box.replaceChildren();
            if (!payload.groups.length) {
                const empty = document.createElement('div');
                empty.className = 'perm-empty';
                empty.textContent = i18n.no_permissions_assigned;
                box.append(empty);
                return;
            }
            payload.groups.forEach(({ group, items }) => {
                const wrap = document.createElement('div');
                wrap.className = 'perm-chip-group';
                const h = document.createElement('h5');
                h.textContent = group;
                const chips = document.createElement('div');
                chips.className = 'perm-chips';
                items.forEach((name) => {
                    const chip = document.createElement('span');
                    chip.className = 'perm-chip';
                    chip.textContent = name;
                    chips.append(chip);
                });
                wrap.append(h, chips);
                box.append(wrap);
            });
        },
    });
}