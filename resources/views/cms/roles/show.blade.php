<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-user-shield"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('roles.modal.show_title') }}</h2>
                <p>{{ __('roles.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('roles.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial"><i class="fa-solid fa-user-shield"></i></div>
                <div>
                    <h3 data-show="name">—</h3>
                    <span class="badge badge--info"><i class="fa-solid fa-shield"></i> <span data-show="guard_name">—</span></span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('roles.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('roles.table.users') }}</dt><dd data-show="users_count">—</dd></div>
                    <div><dt>{{ __('roles.table.permissions') }}</dt><dd data-show="permissions_count">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-key"></i> {{ __('roles.modal.permissions_section') }}</h4>
                <div data-show-groups></div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('roles.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('roles.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('roles.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('roles.actions.close') }}</button>
        </footer>
    </div>
</div>