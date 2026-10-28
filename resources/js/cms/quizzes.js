import { createCrudModule, setText, fmtDate } from './crud-module';
import { initQuestionBuilder } from './question-builder';

const app = document.getElementById('quizzesApp');

if (app) {
    const i18n = JSON.parse(app.dataset.i18n);
    const builder = initQuestionBuilder(app);
    let lessons = [];

    function fillLessons(form, courseId) {
        const select = form.querySelector('[data-lesson-select]');
        const items = lessons.filter((l) => String(l.course.id) === String(courseId));
        select.replaceChildren(
            new Option(i18n.select_lesson, ''),
            ...items.map((l) => new Option(`${l.title_ar} — ${l.title_en}`, l.id)),
        );
        select.disabled = !courseId;
    }

    createCrudModule(app, {
        onAction(action, id) {
            if (action === 'questions') builder?.open(id);
        },

        init() {
            const form = app.querySelector('#createForm');
            form.querySelector('[data-course-select]').addEventListener('change', (e) => fillLessons(form, e.target.value));
        },

        onCreate(form, data) {
            lessons = data.options.lessons;
            const courses = [...new Map(lessons.map((l) => [l.course.id, l.course])).values()];

            form.querySelector('[data-course-select]').replaceChildren(
                new Option(i18n.select_course, ''),
                ...courses.map((c) => new Option(`${c.title_ar} — ${c.title_en}`, c.id)),
            );
            fillLessons(form, '');
            form.querySelector('[data-no-lessons]').hidden = lessons.length > 0;
        },

        onEdit(form, payload) {
            const q = payload.data;
            ['title_ar', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed'].forEach((f) => { form.elements[f].value = q[f]; });
            form.querySelector('[data-edit-course]').textContent = q.course ? `${q.course.title_ar} — ${q.course.title_en}` : '—';
            form.querySelector('[data-edit-lesson]').textContent = q.lesson ? `${q.lesson.title_ar} — ${q.lesson.title_en}` : '—';
            form.querySelector('[data-attempts-note]').hidden = !q.has_attempts;
        },

        onShow(modal, payload) {
            const q = payload.data;
            setText(modal, 'title_ar', q.title_ar);
            setText(modal, 'title_en', q.title_en);
            setText(modal, 'course', q.course ? `${q.course.title_ar} — ${q.course.title_en}` : null);
            setText(modal, 'lesson', q.lesson ? `${q.lesson.title_ar} — ${q.lesson.title_en}` : null);
            setText(modal, 'questions', q.questions_count);
            setText(modal, 'points', q.total_points);
            setText(modal, 'pass_mark', `${q.pass_mark}%`);
            setText(modal, 'time_limit', q.time_limit_minutes > 0 ? `${q.time_limit_minutes} ${i18n.minutes}` : i18n.unlimited);
            setText(modal, 'attempts', q.attempts_allowed > 0 ? q.attempts_allowed : i18n.unlimited);
            setText(modal, 'created_at', q.created_at);
            setText(modal, 'updated_at', q.updated_at);

            const ready = modal.querySelector('[data-show-ready]');
            ready.textContent = q.questions_count > 0 ? i18n.ready : i18n.not_ready;
            ready.className = `badge badge--${q.questions_count > 0 ? 'success' : 'muted'}`;
        },
    });
}