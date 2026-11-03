<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-chart-line"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('progress.modal.show_title') }}</h2>
                <p>{{ __('progress.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('progress.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('progress.modal.summary_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('progress.fields.student') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('progress.fields.email') }}</dt><dd dir="ltr" data-show="email">—</dd></div>
                    <div><dt>{{ __('progress.table.course') }}</dt><dd data-show="course">—</dd></div>
                    <div><dt>{{ __('progress.fields.enrollment') }}</dt><dd data-show="enrollment">—</dd></div>
                    <div><dt>{{ __('progress.table.progress') }}</dt><dd dir="ltr" data-show="progress">—</dd></div>
                    <div><dt>{{ __('progress.table.state') }}</dt><dd><span data-show-state class="badge badge--muted">—</span></dd></div>
                    <div><dt>{{ __('progress.fields.finished_at') }}</dt><dd dir="ltr" data-show="finished_at">—</dd></div>
                    <div><dt>{{ __('progress.table.updated_at') }}</dt><dd dir="ltr" data-show="updated_at">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-list-check"></i> {{ __('progress.modal.lessons_section') }}</h4>
                <div class="progress-lessons" data-lessons></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('progress.ui.close') }}</button>
        </footer>
    </div>
</div>
