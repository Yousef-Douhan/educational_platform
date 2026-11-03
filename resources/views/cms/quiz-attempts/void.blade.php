<div class="crud-modal" id="voidModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="voidModalTitle">
    <form class="crud-modal__dialog" id="voidForm" novalidate>
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-danger"><i class="fa-solid fa-ban"></i></div>
            <div class="crud-modal__heading">
                <h2 id="voidModalTitle">{{ __('quiz_attempts.modal.void_title') }}</h2>
                <p>{{ __('quiz_attempts.modal.void_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('quiz_attempts.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="form-group form-group--full">
                <label for="void_reason" class="form-label">{{ __('quiz_attempts.fields.reason') }} <span class="required">*</span></label>
                <textarea id="void_reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                <small class="form-hint">{{ __('quiz_attempts.hints.void') }}</small>
                <div class="field-error" data-error-for="reason"></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('quiz_attempts.ui.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-ban btn-icon"></i><span class="spinner"></span>
                <span>{{ __('quiz_attempts.actions.void') }}</span>
            </button>
        </footer>
    </form>
</div>
