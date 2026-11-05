@php $p = $prefix; @endphp

<div class="form-grid">
    <div class="form-group form-group--full">
        <label for="{{ $p }}_name" class="form-label">{{ __('certificate-templates.fields.name') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-certificate"></i>
            <input type="text" id="{{ $p }}_name" name="name" class="form-control" maxlength="255" required
                   placeholder="{{ __('certificate-templates.placeholders.name') }}">
        </div>
        <div class="field-error" data-error-for="name"></div>
    </div>

    <div class="form-group form-group--full">
        <span class="form-label">
            {{ __('certificate-templates.fields.design') }}
            @if ($p === 'create') <span class="required">*</span> @endif
        </span>
        <div class="design-box">
            <div class="design-preview" data-design-preview><i class="fa-regular fa-image"></i></div>
            <div class="upload-actions">
                <label for="{{ $p }}_design" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('certificate-templates.ui.choose_image') }}
                </label>
                <input type="file" id="{{ $p }}_design" name="design" accept="image/jpeg,image/png,image/webp" data-design-input hidden>
                <small class="form-hint">{{ __('certificate-templates.hints.design') }}</small>
                @if ($p === 'edit')
                    <small class="form-hint">{{ __('certificate-templates.hints.design_keep') }}</small>
                @endif
            </div>
        </div>
        <div class="field-error" data-error-for="design"></div>
    </div>

    <div class="form-group form-group--full">
        <label class="switch-row">
            <span class="switch switch--ok">
                <input type="checkbox" name="is_default" value="1">
                <span class="switch__slider"></span>
            </span>
            <span>{{ __('certificate-templates.fields.is_default') }}</span>
        </label>
        <small class="form-hint">{{ __('certificate-templates.hints.is_default') }}</small>
    </div>
        <details class="form-group form-group--full layout-box">
        <summary><i class="fa-solid fa-sliders"></i> {{ __('certificate-templates.layout.title') }}</summary>
        <p class="form-hint">{{ __('certificate-templates.layout.help') }}</p>

        <div class="form-grid">
            @foreach ([['area_left', 0], ['area_width', 297], ['middle_top', 77], ['qr_top', 142]] as [$key, $default])
                <div class="form-group">
                    <label for="{{ $p }}_layout_{{ $key }}" class="form-label">{{ __('certificate-templates.layout.' . $key) }}</label>
                    <input type="number" step="0.5" id="{{ $p }}_layout_{{ $key }}" name="layout[{{ $key }}]" class="form-control"
                           placeholder="{{ $default }}" dir="ltr">
                    <div class="field-error" data-error-for="layout.{{ $key }}"></div>
                </div>
            @endforeach

            <div class="form-group">
                <label for="{{ $p }}_layout_text_color" class="form-label">{{ __('certificate-templates.layout.text_color') }}</label>
                <input type="color" id="{{ $p }}_layout_text_color" name="layout[text_color]" value="#111827" class="color-input">
            </div>
            <div class="form-group">
                <label for="{{ $p }}_layout_accent_color" class="form-label">{{ __('certificate-templates.layout.accent_color') }}</label>
                <input type="color" id="{{ $p }}_layout_accent_color" name="layout[accent_color]" value="#1e1b4b" class="color-input">
            </div>

            <div class="form-group form-group--full layout-flags">
                @foreach (['hide_header', 'hide_date', 'hide_signature', 'hide_qr'] as $flag)
                    <label class="check-row">
                        <input type="checkbox" name="layout[{{ $flag }}]" value="1">
                        <span>{{ __('certificate-templates.layout.' . $flag) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </details>
</div>