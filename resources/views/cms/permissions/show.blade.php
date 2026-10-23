<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-key"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('permissions.modal.show_title') }}</h2>
                <p>{{ __('permissions.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('permissions.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial"><i class="fa-solid fa-key"></i></div>
                <div>
                    <h3 data-show="name" dir="ltr">—</h3>
                    <span class="badge badge--info"><i class="fa-solid fa-shield"></i> <span data-show="guard_name">—</span></span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-user-shield"></i> {{ __('permissions.modal.roles_section') }}</h4>
                <div class="perm-chips" data-show-roles></div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('permissions.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('permissions.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('permissions.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('permissions.actions.close') }}</button>
        </footer>
    </div>
</div>