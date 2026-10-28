<div class="crud-modal" id="trashModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="trashModalTitle">
    <div class="crud-modal__dialog crud-modal__dialog--lg">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-danger"><i class="fa-solid fa-trash-can-arrow-up"></i></div>
            <div class="crud-modal__heading">
                <h2 id="trashModalTitle">{{ __('quizzes.modal.trash_title') }}</h2>
                <p>{{ __('quizzes.modal.trash_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('quizzes.ui.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="alert-box alert-box--danger">
                <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('quizzes.trash.warning') }}</span>
            </div>

            <div class="search-box search-box--block">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="trashSearch" class="form-control" autocomplete="off"
                       placeholder="{{ __('quizzes.filters.search_placeholder') }}">
            </div>

            <div class="trash-card" id="trashCard">
                <div id="trashContainer"></div>
                @include('cms.partials.table-skeleton', ['rows' => 3])
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('quizzes.ui.close') }}</button>
        </footer>
    </div>
</div>