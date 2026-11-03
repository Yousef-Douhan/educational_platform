<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-clipboard-question"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('quiz_attempts.modal.show_title') }}</h2>
                <p>{{ __('quiz_attempts.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('quiz_attempts.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('quiz_attempts.modal.attempt_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('quiz_attempts.fields.student') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.email') }}</dt><dd dir="ltr" data-show="email">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.table.quiz') }}</dt><dd data-show="quiz">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.course') }}</dt><dd data-show="course">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.table.status') }}</dt><dd><span data-show-status class="badge badge--muted">—</span></dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.attempt_number') }}</dt><dd dir="ltr" data-show="number">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.table.score') }}</dt><dd dir="ltr" data-show="score">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.pass_mark') }}</dt><dd dir="ltr" data-show="pass_mark">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.table.started_at') }}</dt><dd dir="ltr" data-show="started_at">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.submitted_at') }}</dt><dd dir="ltr" data-show="submitted_at">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.expires_at') }}</dt><dd dir="ltr" data-show="expires_at">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.duration') }}</dt><dd data-show="duration">—</dd></div>
                </dl>
            </div>

            <div class="detail-section" data-voided-only>
                <h4><i class="fa-solid fa-ban"></i> {{ __('quiz_attempts.modal.void_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('quiz_attempts.fields.voided_by') }}</dt><dd data-show="voided_by">—</dd></div>
                    <div><dt>{{ __('quiz_attempts.fields.voided_at') }}</dt><dd dir="ltr" data-show="voided_at">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('quiz_attempts.fields.reason') }}</dt><dd data-show="void_reason">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-list-check"></i> {{ __('quiz_attempts.modal.answers_section') }}</h4>
                <div class="attempt-answers" data-answers></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('quiz_attempts.ui.close') }}</button>
        </footer>
    </div>
</div>
