<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-video"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('live_sessions.modal.show_title') }}</h2>
                <p>{{ __('live_sessions.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('live_sessions.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="session-hero">
                <strong class="session-hero__title" data-show="title">—</strong>
                <span class="badge badge--muted" data-show-phase>—</span>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('live_sessions.modal.details_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('live_sessions.fields.course') }}</dt><dd data-show="course">—</dd></div>
                    <div><dt>{{ __('live_sessions.fields.title_ar') }}</dt><dd dir="rtl" data-show="title_ar">—</dd></div>
                    <div><dt>{{ __('live_sessions.fields.title_en') }}</dt><dd dir="ltr" data-show="title_en">—</dd></div>
                    <div><dt>{{ __('live_sessions.fields.duration_minutes') }}</dt><dd data-show="duration">—</dd></div>
                    <div><dt>{{ __('live_sessions.fields.scheduled_at') }}</dt><dd dir="ltr" data-show="scheduled_at_label">—</dd></div>
                    <div><dt>{{ __('live_sessions.table.ends_at') }}</dt><dd dir="ltr" data-show="ends_at_label">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('live_sessions.fields.meeting_url') }}</dt><dd dir="ltr"><span data-show-link>—</span></dd></div>
                </dl>
            </div>

            <div class="detail-section" data-cancelled-only hidden>
                <h4><i class="fa-solid fa-ban"></i> {{ __('live_sessions.modal.cancel_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('live_sessions.table.cancelled_at') }}</dt><dd dir="ltr" data-show="cancelled_at_label">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('live_sessions.fields.cancel_reason') }}</dt><dd data-show="cancel_reason">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('live_sessions.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('live_sessions.table.created_at') }}</dt><dd dir="ltr" data-show="created_at_label">—</dd></div>
                    <div><dt>{{ __('live_sessions.table.updated_at') }}</dt><dd dir="ltr" data-show="updated_at_label">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('live_sessions.ui.close') }}</button>
        </footer>
    </div>
</div>
