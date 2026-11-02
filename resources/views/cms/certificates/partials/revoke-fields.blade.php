<div class="form-grid">
    <div class="form-group form-group--full">
        <div class="alert-box alert-box--danger">
            <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('certificates.ui.revoke_warning') }}</span>
        </div>
    </div>
    <div class="form-group form-group--full">
        <label for="revoke_reason" class="form-label">{{ __('certificates.fields.reason') }} <span class="required">*</span></label>
        <textarea id="revoke_reason" name="reason" rows="3" class="form-control" maxlength="500" required
                  placeholder="{{ __('certificates.ui.reason_placeholder') }}"></textarea>
        <div class="field-error" data-error-for="reason"></div>
    </div>
</div>