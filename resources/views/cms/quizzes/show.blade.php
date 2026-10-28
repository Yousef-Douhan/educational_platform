<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-circle-question"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('quizzes.modal.show_title') }}</h2>
                <p>{{ __('quizzes.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('quizzes.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial"><i class="fa-solid fa-circle-question"></i></div>
                <div>
                    <h3 data-show="title_ar" dir="rtl">—</h3>
                    <span class="profile-hero__email" data-show="title_en" dir="ltr">—</span>
                    <div class="hero-badges"><span data-show-ready class="badge badge--muted">—</span></div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-graduation-cap"></i> {{ __('quizzes.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('quizzes.ui.course') }}</dt><dd data-show="course">—</dd></div>
                    <div><dt>{{ __('quizzes.fields.lesson') }}</dt><dd data-show="lesson">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-sliders"></i> {{ __('quizzes.modal.stats_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('quizzes.table.questions') }}</dt><dd data-show="questions">—</dd></div>
                    <div><dt>{{ __('quizzes.ui.stats.points') }}</dt><dd data-show="points">—</dd></div>
                    <div><dt>{{ __('quizzes.fields.pass_mark') }}</dt><dd data-show="pass_mark">—</dd></div>
                    <div><dt>{{ __('quizzes.table.time_limit') }}</dt><dd data-show="time_limit">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('quizzes.fields.attempts') }}</dt><dd data-show="attempts">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('quizzes.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('quizzes.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('quizzes.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('quizzes.ui.close') }}</button>
        </footer>
    </div>
</div>