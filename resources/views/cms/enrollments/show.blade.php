<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('enrollments.modal.show_title') }}</h2>
                <p>{{ __('enrollments.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('enrollments.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial" data-show-initial>?</div>
                <div>
                    <h3 data-show="user_name">—</h3>
                    <span class="profile-hero__email" data-show="user_email" dir="ltr">—</span>
                    <div class="hero-badges">
                        <span data-show-status class="badge badge--muted">—</span>
                        <span data-show-access class="badge badge--muted">—</span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-graduation-cap"></i> {{ __('enrollments.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div class="detail-grid__full"><dt>{{ __('enrollments.fields.course') }}</dt><dd data-show="course_title">—</dd></div>
                    <div><dt>{{ __('enrollments.table.enrolled_at') }}</dt><dd data-show="enrolled_at">—</dd></div>
                    <div><dt>{{ __('enrollments.table.expires_at') }}</dt><dd data-show="expires_at">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('enrollments.modal.days_left') }}</dt><dd data-show="days_left">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('enrollments.actions.close') }}</button>
        </footer>
    </div>
</div>