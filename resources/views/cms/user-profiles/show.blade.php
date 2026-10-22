<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-address-card"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('user-profiles.modal.show_title') }}</h2>
                <p>{{ __('user-profiles.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('user-profiles.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl" data-show-avatar></div>
                <div>
                    <h3 data-show="name">—</h3>
                    <span class="profile-hero__email"><i class="fa-regular fa-envelope"></i> <span data-show="email">—</span></span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-address-book"></i> {{ __('user-profiles.modal.contact_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('user-profiles.fields.phone') }}</dt><dd data-show="phone" dir="ltr">—</dd></div>
                    <div><dt>{{ __('user-profiles.fields.website') }}</dt><dd><a data-show="website" target="_blank" rel="noopener noreferrer" dir="ltr">—</a></dd></div>
                    <div class="detail-grid__full"><dt>{{ __('user-profiles.fields.social_links') }}</dt><dd data-show="social_links" dir="ltr">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-align-left"></i> {{ __('user-profiles.modal.bio_section') }}</h4>
                <p class="detail-bio" data-show="bio">—</p>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('user-profiles.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('user-profiles.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('user-profiles.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('user-profiles.actions.close') }}</button>
        </footer>
    </div>
</div>