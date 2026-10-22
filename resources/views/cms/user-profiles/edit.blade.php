<div class="crud-modal" id="editModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <form class="crud-modal__dialog" id="editForm" novalidate enctype="multipart/form-data">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-warning"><i class="fa-solid fa-user-pen"></i></div>
            <div class="crud-modal__heading">
                <h2 id="editModalTitle">{{ __('user-profiles.modal.edit_title') }}</h2>
                <p>{{ __('user-profiles.modal.edit_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('user-profiles.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            @include('cms.user-profiles.partials.form-fields', ['prefix' => 'edit'])
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('user-profiles.actions.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check btn-icon"></i><span class="spinner"></span>
                {{ __('user-profiles.actions.update') }}
            </button>
        </footer>
    </form>
</div>