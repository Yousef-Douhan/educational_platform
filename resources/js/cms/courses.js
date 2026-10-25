import { createCrudModule, setText, fmtDate } from './crud-module';
import { showFieldErrors } from './crud-common';
import { initCurriculum } from './curriculum';

const app = document.getElementById('coursesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const TONES = { draft: 'muted', pending_review: 'warning', published: 'success', archived: 'danger' };
    const REPEATERS = {
        requirements: { ar: 'requirement_ar', en: 'requirement_en' },
        learning_outcomes: { ar: 'outcome_ar', en: 'outcome_en' },
    };
    let rowSeq = 0;

    /* ---------- أدوات ---------- */
    function fillSelect(select, items, label, selected = null, placeholder = null) {
        select.replaceChildren();
        if (placeholder !== null) select.append(new Option(placeholder, ''));
        items.forEach((it) => select.append(new Option(label(it), it.id, false, Number(selected) === it.id)));
    }

    function setPreview(form, url) {
        const box = form.querySelector('[data-image-preview]');
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

    function renderCoInstructors(form, instructors, selected = []) {
        const box = form.querySelector('[data-co-instructors]');
        box.replaceChildren();
        if (!instructors.length) {
            const empty = document.createElement('span');
            empty.className = 'chip-empty';
            empty.textContent = i18n.no_instructors;
            box.append(empty);
            return;
        }
        instructors.forEach((u) => {
            const label = document.createElement('label');
            label.className = 'chip-option';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'co_instructors[]';
            cb.value = u.id;
            cb.checked = selected.includes(u.id);
            const text = document.createElement('span');
            text.textContent = u.name;
            label.append(cb, text);
            box.append(label);
        });
    }

    function makeInput(name, value, placeholder, dir) {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = name;
        input.value = value ?? '';
        input.maxLength = 1000;
        input.dir = dir;
        input.className = 'form-control';
        input.placeholder = placeholder;
        return input;
    }

    function addRow(form, kind, values = {}) {
        const cfg = REPEATERS[kind];
        const i = rowSeq++;
        const row = document.createElement('div');
        row.className = 'rep-row';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'rep-remove';
        remove.dataset.repeaterRemove = '';
        remove.setAttribute('aria-label', i18n.remove_row);
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-trash-can';
        remove.append(icon);

        row.append(
            makeInput(`${kind}[${i}][${cfg.ar}]`, values[cfg.ar], i18n.placeholder_ar, 'rtl'),
            makeInput(`${kind}[${i}][${cfg.en}]`, values[cfg.en], i18n.placeholder_en, 'ltr'),
            remove,
        );
        form.querySelector(`[data-repeater="${kind}"]`).append(row);
    }

    function resetRepeaters(form, data = {}) {
        Object.keys(REPEATERS).forEach((kind) => {
            form.querySelector(`[data-repeater="${kind}"]`).replaceChildren();
            (data[kind] || []).forEach((row) => addRow(form, kind, row));
        });
    }

    function fillStatus(form, allowed, current = null) {
        const select = form.querySelector('[data-status-select]');
        const hint = form.querySelector('[data-status-locked]');
        const locked = current && !allowed.includes(current);

        select.replaceChildren(...(locked ? [current] : allowed).map((s) => new Option(i18n.statuses[s], s)));
        select.disabled = Boolean(locked);
        hint.hidden = !locked;
        if (current && !locked) select.value = current;
    }

    function prepareForm(form, options, course = null) {
        fillSelect(form.querySelector('[data-category-select]'), options.categories,
            (c) => `${c.name_ar} — ${c.name_en}`, course?.category_id, i18n.select_category);

        const wrap = form.querySelector('[data-instructor-wrap]');
        const select = form.querySelector('[data-instructor-select]');
        wrap.hidden = !options.can_pick_instructor;
        select.disabled = !options.can_pick_instructor; // لا يُرسل عند إخفائه
        fillSelect(select, options.instructors, (u) => u.name, course?.instructor_id, i18n.select_instructor);

        const level = form.querySelector('[data-level-select]');
        level.replaceChildren(...options.levels.map((l) => new Option(i18n.levels[l], l)));
        level.value = course?.level || 'beginner';

        fillStatus(form, options.statuses, course?.status ?? null);
        if (!course) form.querySelector('[data-status-select]').value = 'draft';
    }

    /* ---------- قوائم العرض ---------- */
    function renderList(ul, rows, key) {
        ul.replaceChildren();
        if (!rows.length) {
            const empty = document.createElement('li');
            empty.className = 'detail-empty';
            empty.style.listStyle = 'none';
            empty.textContent = i18n.none;
            ul.append(empty);
            return;
        }
        rows.forEach((r) => {
            const li = document.createElement('li');
            li.textContent = r[`${key}_ar`];
            const en = document.createElement('span');
            en.dir = 'ltr';
            en.textContent = r[`${key}_en`];
            li.append(en);
            ul.append(li);
        });
    }

    /* ---------- منشئ المنهج ---------- */
    const curriculum = initCurriculum(app);

    createCrudModule(app, {
        // إجراءات إضافية في قائمة الدورة (مثل "المنهج")
        onAction(action, id) {
            if (action === 'curriculum') curriculum?.open(id);
        },

        init() {
            ['#createForm', '#editForm'].forEach((sel) => {
                const form = app.querySelector(sel);
                const input = form.querySelector('[data-image-input]');

                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) return;
                    if (file.size > 2 * 1024 * 1024) {
                        input.value = '';
                        return showFieldErrors(form, { image: [i18n.image_too_large] });
                    }
                    setPreview(form, URL.createObjectURL(file));
                });

                form.querySelector('[data-remove-image]')?.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        input.value = '';
                        setPreview(form, null);
                    }
                });

                form.addEventListener('click', (e) => {
                    const add = e.target.closest('[data-repeater-add]');
                    if (add) return addRow(form, add.dataset.repeaterAdd);
                    e.target.closest('[data-repeater-remove]')?.closest('.rep-row').remove();
                });
            });
        },

        // حذف الصفوف الفارغة كليًا قبل الإرسال
        beforeSubmit(form) {
            form.querySelectorAll('.rep-row').forEach((row) => {
                const empty = [...row.querySelectorAll('input')].every((i) => !i.value.trim());
                if (empty) row.remove();
            });
        },

        onCreate(form, data) {
            prepareForm(form, data.options);
            setPreview(form, null);
            renderCoInstructors(form, data.options.instructors);
            resetRepeaters(form);
            form.elements.price.value = 0;
        },

        onEdit(form, payload) {
            const c = payload.data;
            ['title_ar', 'title_en', 'slug', 'description_ar', 'description_en'].forEach((f) => { form.elements[f].value = c[f] ?? ''; });
            form.elements.price.value = c.price ?? 0;
            form.elements.discount_price.value = c.discount_price ?? '';

            prepareForm(form, payload.options, c);
            renderCoInstructors(form, payload.options.instructors, payload.co_instructor_ids);
            resetRepeaters(form, { requirements: c.requirements, learning_outcomes: c.learning_outcomes });

            setPreview(form, c.image_src);
            form.querySelector('[data-remove-image-wrap]').hidden = !c.image_url;
        },

        onShow(modal, payload) {
            const c = payload.data;
            setText(modal, 'title_ar', c.title_ar);
            setText(modal, 'title_en', c.title_en);
            setText(modal, 'slug', c.slug);
            setText(modal, 'category_name', c.category ? `${c.category.name_ar} — ${c.category.name_en}` : null);
            setText(modal, 'instructor_name', c.instructor ? `${c.instructor.name} (${c.instructor.email})` : null);
            setText(modal, 'description_ar', c.description_ar);
            setText(modal, 'description_en', c.description_en);
            setText(modal, 'created_at', fmtDate(c.created_at));
            setText(modal, 'updated_at', fmtDate(c.updated_at));

            const status = modal.querySelector('[data-show-status]');
            status.textContent = i18n.statuses[c.status];
            status.className = `badge badge--${TONES[c.status] || 'muted'}`;
            modal.querySelector('[data-show-level]').textContent = i18n.levels[c.level];

            const old = modal.querySelector('[data-show="old_price"]');
            const discounted = !c.is_free && Number(c.final_price) < Number(c.price);
            setText(modal, 'price', c.is_free ? i18n.free : Number(c.final_price).toFixed(2));
            old.hidden = !discounted;
            old.textContent = discounted ? Number(c.price).toFixed(2) : '';

            const cover = modal.querySelector('[data-show-image]');
            cover.replaceChildren();
            if (c.image_src) {
                const img = document.createElement('img');
                img.src = c.image_src;
                img.alt = '';
                cover.append(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-graduation-cap';
                cover.append(icon);
            }

            const team = modal.querySelector('[data-show-co-instructors]');
            team.replaceChildren();
            if (!c.co_instructors.length) {
                const empty = document.createElement('span');
                empty.className = 'detail-empty';
                empty.textContent = i18n.no_co_instructors;
                team.append(empty);
            }
            c.co_instructors.forEach((u) => {
                const chip = document.createElement('span');
                chip.className = 'chip';
                chip.textContent = u.name;
                team.append(chip);
            });

            renderList(modal.querySelector('[data-show-requirements]'), c.requirements, 'requirement');
            renderList(modal.querySelector('[data-show-outcomes]'), c.learning_outcomes, 'outcome');
        },
    });
}