@php $p = $prefix; @endphp

<div class="alert-box alert-box--info" data-locked-note hidden>
    <i class="fa-solid fa-lock"></i><span>{{ __('coupons.hints.locked') }}</span>
</div>

<div class="form-grid">
    <div class="form-group form-group--full">
        <label for="{{ $p }}_code" class="form-label">{{ __('coupons.fields.code') }} <span class="required">*</span></label>
        <div class="code-row">
            <div class="input-icon">
                <i class="fa-solid fa-ticket"></i>
                <input type="text" id="{{ $p }}_code" name="code" class="form-control code-input" maxlength="50" required
                       dir="ltr" autocomplete="off" spellcheck="false" placeholder="SUMMER-2026">
            </div>
            <button type="button" class="btn btn-light" data-action="generate-code">
                <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('coupons.actions.generate') }}
            </button>
        </div>
        <small class="form-hint">{{ __('coupons.hints.code') }}</small>
        <div class="field-error" data-error-for="code"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_discount_type" class="form-label">{{ __('coupons.fields.discount_type') }} <span class="required">*</span></label>
        <select id="{{ $p }}_discount_type" name="discount_type" class="form-control" required>
            @foreach (\App\Models\Coupon::TYPES as $t)
                <option value="{{ $t }}">{{ __('coupons.types.' . $t) }}</option>
            @endforeach
        </select>
        <div class="field-error" data-error-for="discount_type"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_discount_value" class="form-label">
            {{ __('coupons.fields.discount_value') }} <span class="required">*</span>
            <span class="unit-badge" data-unit-badge>%</span>
        </label>
        <input type="number" id="{{ $p }}_discount_value" name="discount_value" class="form-control" required
               min="0.01" max="100" step="0.01" dir="ltr" inputmode="decimal" placeholder="20">
        <div class="field-error" data-error-for="discount_value"></div>
    </div>

    <div class="form-group" data-percentage-only>
        <label for="{{ $p }}_max_discount" class="form-label">{{ __('coupons.fields.max_discount') }}</label>
        <input type="number" id="{{ $p }}_max_discount" name="max_discount" class="form-control"
               min="0.01" step="0.01" dir="ltr" inputmode="decimal" placeholder="{{ __('coupons.placeholders.none') }}">
        <small class="form-hint">{{ __('coupons.hints.max_discount') }}</small>
        <div class="field-error" data-error-for="max_discount"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_min_order_amount" class="form-label">{{ __('coupons.fields.min_order_amount') }}</label>
        <input type="number" id="{{ $p }}_min_order_amount" name="min_order_amount" class="form-control"
               min="0" step="0.01" dir="ltr" inputmode="decimal" placeholder="{{ __('coupons.placeholders.none') }}">
        <div class="field-error" data-error-for="min_order_amount"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_starts_at" class="form-label">{{ __('coupons.fields.starts_at') }}</label>
        <input type="datetime-local" id="{{ $p }}_starts_at" name="starts_at" class="form-control" dir="ltr">
        <small class="form-hint">{{ __('coupons.hints.starts_at') }}</small>
        <div class="field-error" data-error-for="starts_at"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_expires_at" class="form-label">{{ __('coupons.fields.expires_at') }}</label>
        <input type="datetime-local" id="{{ $p }}_expires_at" name="expires_at" class="form-control" dir="ltr">
        <small class="form-hint">{{ __('coupons.hints.expires_at') }}</small>
        <div class="field-error" data-error-for="expires_at"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_usage_limit" class="form-label">{{ __('coupons.fields.usage_limit') }}</label>
        <input type="number" id="{{ $p }}_usage_limit" name="usage_limit" class="form-control"
               min="1" max="1000000" step="1" dir="ltr" inputmode="numeric" placeholder="{{ __('coupons.placeholders.unlimited') }}">
        <small class="form-hint">{{ __('coupons.hints.usage_limit') }}</small>
        <div class="field-error" data-error-for="usage_limit"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_per_user_limit" class="form-label">{{ __('coupons.fields.per_user_limit') }}</label>
        <input type="number" id="{{ $p }}_per_user_limit" name="per_user_limit" class="form-control"
               min="1" max="1000000" step="1" dir="ltr" inputmode="numeric" placeholder="{{ __('coupons.placeholders.unlimited') }}">
        <small class="form-hint">{{ __('coupons.hints.per_user_limit') }}</small>
        <div class="field-error" data-error-for="per_user_limit"></div>
    </div>

    <div class="form-group form-group--full">
        <label class="switch-row">
            <span class="switch switch--ok">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="switch__slider"></span>
            </span>
            <span>{{ __('coupons.fields.is_active') }}</span>
        </label>
        <small class="form-hint">{{ __('coupons.hints.is_active') }}</small>
    </div>
</div>
