<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-certificate"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('certificate-templates.modal.show_title') }}</h2>
                <p>{{ __('certificate-templates.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('certificate-templates.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="design-preview design-preview--lg" data-show-design><i class="fa-regular fa-image"></i></div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('certificate-templates.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('certificate-templates.fields.name') }}</dt><dd data-show="name">—</dd></div>
                    <div><dt>{{ __('certificate-templates.table.certificates') }}</dt><dd data-show="certificates_count">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('certificate-templates.fields.is_default') }}</dt>
                        <dd><span data-show-default class="badge badge--muted">—</span></dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('certificate-templates.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('certificate-templates.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('certificate-templates.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('certificate-templates.ui.close') }}</button>
        </footer>
    </div>
</div>