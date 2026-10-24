<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-graduation-cap"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('courses.modal.show_title') }}</h2>
                <p>{{ __('courses.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('courses.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="course-cover" data-show-image><i class="fa-solid fa-graduation-cap"></i></div>
                <div>
                    <h3 data-show="title_ar" dir="rtl">—</h3>
                    <span class="profile-hero__email" data-show="title_en" dir="ltr">—</span>
                    <div class="hero-badges">
                        <span data-show-status class="badge badge--muted">—</span>
                        <span data-show-level class="badge badge--info">—</span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('courses.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('courses.fields.instructor') }}</dt><dd data-show="instructor_name">—</dd></div>
                    <div><dt>{{ __('courses.fields.category') }}</dt><dd data-show="category_name">—</dd></div>
                    <div><dt>{{ __('courses.fields.price') }}</dt>
                        <dd><span data-show="price">—</span> <s class="old-price" data-show="old_price" hidden></s></dd></div>
                    <div><dt>{{ __('courses.fields.slug') }}</dt><dd data-show="slug" dir="ltr">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-user-group"></i> {{ __('courses.modal.team_section') }}</h4>
                <div class="chip-list" data-show-co-instructors></div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-align-left"></i> {{ __('courses.modal.description_section') }}</h4>
                <p class="detail-bio" data-show="description_ar" dir="rtl">—</p>
                <p class="detail-bio detail-bio--gap" data-show="description_en" dir="ltr">—</p>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-list-check"></i> {{ __('courses.fields.requirements') }}</h4>
                <ul class="detail-list" data-show-requirements></ul>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-bullseye"></i> {{ __('courses.fields.learning_outcomes') }}</h4>
                <ul class="detail-list" data-show-outcomes></ul>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('courses.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('courses.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('courses.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('courses.actions.close') }}</button>
        </footer>
    </div>
</div>