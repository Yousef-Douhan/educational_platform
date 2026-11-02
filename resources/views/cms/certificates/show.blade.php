<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-award"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('certificates.modal.show_title') }}</h2>
                <p>{{ __('certificates.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close
                aria-label="{{ __('certificates.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial"><i class="fa-solid fa-award"></i></div>
                <div>
                    <h3 data-show="student_name">—</h3>
                    <span class="profile-hero__email" data-show="student_email" dir="ltr">—</span>
                    <div class="hero-badges"><span data-show-status class="badge badge--muted">—</span></div>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('certificates.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div class="detail-grid__full">
                        <dt>{{ __('certificates.table.code') }}</dt>
                        <dd data-show="code" dir="ltr">—</dd>
                    </div>
                    <div class="detail-grid__full">
                        <dt>{{ __('certificates.fields.course') }}</dt>
                        <dd data-show="course">—</dd>
                    </div>
                    <div>
                        <dt>{{ __('certificates.fields.template') }}</dt>
                        <dd data-show="template">—</dd>
                    </div>
                    <div>
                        <dt>{{ __('certificates.ui.issued_by') }}</dt>
                        <dd data-show="issued_by">—</dd>
                    </div>
                    <div>
                        <dt>{{ __('certificates.table.issued_at') }}</dt>
                        <dd data-show="issued_at">—</dd>
                    </div>
                </dl>
            </div>

            <div class="detail-section" data-revoked-block hidden>
                <h4><i class="fa-solid fa-ban"></i> {{ __('certificates.modal.revoked_section') }}</h4>
                <dl class="detail-grid">
                    <div>
                        <dt>{{ __('certificates.ui.revoked_at') }}</dt>
                        <dd data-show="revoked_at">—</dd>
                    </div>
                    <div>
                        <dt>{{ __('certificates.fields.reason') }}</dt>
                        <dd data-show="revoked_reason">—</dd>
                    </div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-link"></i> {{ __('certificates.ui.verification_link') }}</h4>
                <div class="link-box">
                    <span class="link-box__url" data-show="verification_url" dir="ltr">—</span>
                    <button type="button" class="btn btn-light btn-sm" data-copy-link>
                        <i class="fa-regular fa-copy"></i> {{ __('certificates.actions.copy') }}
                    </button>
                </div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <a class="btn btn-primary" data-show-download href="#">
                <i class="fa-solid fa-file-pdf"></i> {{ __('certificates.actions.download') }}
            </a>
            <a class="btn btn-light" data-show-download-ar
                href="#">{{ __('certificates.actions.download_ar') }}</a>
            <a class="btn btn-light" data-show-download-en
                href="#">{{ __('certificates.actions.download_en') }}</a>
            <button type="button" class="btn btn-light" data-modal-close>{{ __('certificates.ui.close') }}</button>
        </footer>
    </div>
</div>
