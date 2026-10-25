import { createCrudModule, setText } from "./crud-module";
import { http, handleError, debounce } from "./crud-common";

const app = document.getElementById("enrollmentsApp");

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const lookupUrl = app.dataset.lookupUrl;
    const TONES = {
        active: "success",
        completed: "primary",
        expired: "muted",
        cancelled: "danger",
    };

    function el(tag, className = "", text = null) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== null) node.textContent = text;
        return node;
    }

    const initial = (name) => (name || "?").trim().charAt(0).toUpperCase();

    /* ---------- اختيار الطالب (بحث غير متزامن) ---------- */
    function initPicker(form) {
        const picker = form.querySelector("[data-user-picker]");
        const search = picker.querySelector("[data-user-search]");
        const searchWrap = picker.querySelector("[data-user-search-wrap]");
        const hidden = picker.querySelector("[data-user-id]");
        const picked = picker.querySelector("[data-picked-user]");
        const results = picker.querySelector("[data-user-results]");
        let seq = 0;

        function reset() {
            hidden.value = "";
            search.value = "";
            picked.hidden = true;
            searchWrap.hidden = false;
            results.hidden = true;
            results.replaceChildren();
        }

        function choose(user) {
            hidden.value = user.id;
            picked.querySelector("[data-picked-initial]").textContent = initial(
                user.name,
            );
            picked.querySelector("[data-picked-name]").textContent = user.name;
            picked.querySelector("[data-picked-email]").textContent =
                user.email;
            picked.hidden = false;
            searchWrap.hidden = true;
            results.hidden = true;
        }

        function render(users) {
            results.replaceChildren();
            if (!users.length) {
                results.append(
                    el("div", "user-results__empty", i18n.no_results),
                );
            }
            users.forEach((u) => {
                const btn = el("button", "user-result");
                btn.type = "button";
                const info = el("div");
                info.append(el("strong", "", u.name), el("small", "", u.email));
                btn.append(
                    el("span", "avatar avatar--initial", initial(u.name)),
                    info,
                );
                btn.addEventListener("click", () => choose(u));
                results.append(btn);
            });
            results.hidden = false;
        }

        const lookup = debounce(async () => {
            const term = search.value.trim();
            if (term.length < 2) {
                results.hidden = true;
                return;
            }
            const id = ++seq;
            try {
                const { data } = await http.get(lookupUrl, {
                    params: { search: term },
                });
                if (id === seq) render(data.data);
            } catch (err) {
                handleError(err);
            }
        }, 350);

        search.addEventListener("input", lookup);
        picker
            .querySelector("[data-user-clear]")
            .addEventListener("click", () => {
                reset();
                search.focus();
            });
        document.addEventListener("click", (e) => {
            if (!picker.contains(e.target)) results.hidden = true;
        });

        return { reset };
    }

    let picker = null;

    createCrudModule(app, {
        init() {
            picker = initPicker(app.querySelector("#createForm"));
        },

        onCreate(form, data) {
            picker.reset();
            const select = form.querySelector("[data-course-select]");
            select.replaceChildren(
                new Option(i18n.select_course, ""),
                ...data.options.courses.map(
                    (c) => new Option(`${c.title_ar} — ${c.title_en}`, c.id),
                ),
            );
            form.elements.expires_at.value = "";
        },

        onEdit(form, payload) {
            const e = payload.data;
            form.querySelector("[data-edit-student]").textContent = e.user.name;
            form.querySelector("[data-edit-email]").textContent = e.user.email;
            form.querySelector("[data-edit-course]").textContent =
                `${e.course.title_ar} — ${e.course.title_en}`;

            const select = form.querySelector("[data-status-select]");
            select.replaceChildren(
                ...payload.statuses.map((s) => new Option(i18n.statuses[s], s)),
            );
            select.value = e.status;

            form.elements.expires_at.value = e.expires_at_input ?? "";
        },

        onShow(modal, payload) {
            const e = payload.data;
            setText(modal, "user_name", e.user.name);
            setText(modal, "user_email", e.user.email);
            setText(
                modal,
                "course_title",
                `${e.course.title_ar} — ${e.course.title_en}`,
            );
            setText(modal, "enrolled_at", e.enrolled_at);
            setText(modal, "expires_at", e.expires_at ?? i18n.lifetime);
            setText(
                modal,
                "days_left",
                e.days_left === null ? "—" : `${e.days_left} ${i18n.days}`,
            );
            modal.querySelector("[data-show-initial]").textContent = initial(
                e.user.name,
            );

            const status = modal.querySelector("[data-show-status]");
            status.textContent = i18n.statuses[e.status];
            status.className = `badge badge--${TONES[e.status] || "muted"}`;

            const access = modal.querySelector("[data-show-access]");
            access.textContent = e.has_access
                ? i18n.access_yes
                : i18n.access_no;
            access.className = `badge badge--${e.has_access ? "success" : "muted"}`;
        },
    });
}
