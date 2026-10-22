<div class="crud-modal" id="trashModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="trashModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-danger"><i class="fa-solid fa-trash-can-arrow-up"></i></div>
            <div class="crud-modal__heading">
                <h2 id="trashModalTitle">{{ __('user-profiles.modal.trash_title') }}</h2>
                <p>{{ __('user-profiles.modal.trash_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('user-profiles.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="alert-box alert-box--danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>{{ __('user-profiles.trash.warning') }}</span>
            </div>

            <div class="search-box search-box--block">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="trashSearch" class="form-control"
                       placeholder="{{ __('user-profiles.filters.search_placeholder') }}" autocomplete="off">
            </div>

            <div class="trash-card" id="trashCard">
                <div id="trashContainer"></div>
                <div class="table-skeleton" aria-hidden="true">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="skeleton-row">
                            <span class="skeleton sk-circle"></span>
                            <span class="skeleton sk-line sk-w-30"></span>
                            <span class="skeleton sk-line sk-w-20"></span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('user-profiles.actions.close') }}</button>
        </footer>
    </div>
</div>