<div class="crud-modal" id="cancelModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <form class="crud-modal__dialog" id="cancelForm" novalidate>
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-danger"><i class="fa-solid fa-ban"></i></div>
            <div class="crud-modal__heading">
                <h2 id="cancelModalTitle">{{ __('live_sessions.modal.cancel_title') }}</h2>
                <p>{{ __('live_sessions.modal.cancel_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('live_sessions.ui.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </header>

        <div class="crud-modal__body">
            <div class="form-group form-group--full">
                <label for="cancel_reason" class="form-label">{{ __('live_sessions.fields.cancel_reason') }} <span class="required">*</span></label>
                <textarea id="cancel_reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                <small class="form-hint">{{ __('live_sessions.hints.cancel') }}</small>
                <div class="field-error" data-error-for="reason"></div>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('live_sessions.ui.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-ban btn-icon"></i><span class="spinner"></span>
                <span>{{ __('live_sessions.actions.cancel_session') }}</span>
            </button>
        </footer>
    </form>
</div>
