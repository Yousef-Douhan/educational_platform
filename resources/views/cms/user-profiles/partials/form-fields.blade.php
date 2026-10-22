@php $p = $prefix; @endphp

<div class="form-grid">
    <div class="form-group form-group--full">
        <label for="{{ $p }}_user_id" class="form-label">
            {{ __('user-profiles.fields.user') }} <span class="required">*</span>
        </label>
        <div class="input-icon">
            <i class="fa-solid fa-user"></i>
            <select id="{{ $p }}_user_id" name="user_id" class="form-control" required></select>
        </div>
        <div class="field-error" data-error-for="user_id"></div>
    </div>

    <div class="form-group form-group--full">
        <span class="form-label">{{ __('user-profiles.fields.avatar') }}</span>
        <div class="upload-box">
            <div class="upload-preview" data-avatar-preview><i class="fa-regular fa-image"></i></div>
            <div class="upload-actions">
                <label for="{{ $p }}_avatar" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('user-profiles.actions.choose_image') }}
                </label>
                <input type="file" id="{{ $p }}_avatar" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input hidden>
                <small class="form-hint">{{ __('user-profiles.hints.avatar') }}</small>
                @if ($p === 'edit')
                    <label class="switch-row" data-remove-avatar-wrap hidden>
                        <span class="switch">
                            <input type="checkbox" name="remove_avatar" value="1" data-remove-avatar>
                            <span class="switch__slider"></span>
                        </span>
                        <span>{{ __('user-profiles.fields.remove_avatar') }}</span>
                    </label>
                @endif
            </div>
        </div>
        <div class="field-error" data-error-for="avatar"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_phone" class="form-label">{{ __('user-profiles.fields.phone') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-phone"></i>
            <input type="tel" id="{{ $p }}_phone" name="phone" class="form-control" maxlength="50" dir="ltr"
                   placeholder="{{ __('user-profiles.placeholders.phone') }}">
        </div>
        <div class="field-error" data-error-for="phone"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_website" class="form-label">{{ __('user-profiles.fields.website') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-globe"></i>
            <input type="url" id="{{ $p }}_website" name="website" class="form-control" maxlength="255" dir="ltr"
                   placeholder="{{ __('user-profiles.placeholders.website') }}">
        </div>
        <div class="field-error" data-error-for="website"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_social_links" class="form-label">{{ __('user-profiles.fields.social_links') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-share-nodes"></i>
            <input type="text" id="{{ $p }}_social_links" name="social_links" class="form-control" maxlength="255" dir="ltr"
                   placeholder="{{ __('user-profiles.placeholders.social_links') }}">
        </div>
        <small class="form-hint">{{ __('user-profiles.hints.social_links') }}</small>
        <div class="field-error" data-error-for="social_links"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_bio" class="form-label">{{ __('user-profiles.fields.bio') }}</label>
        <textarea id="{{ $p }}_bio" name="bio" rows="4" class="form-control"
                  placeholder="{{ __('user-profiles.placeholders.bio') }}"></textarea>
        <div class="field-error" data-error-for="bio"></div>
    </div>
</div>