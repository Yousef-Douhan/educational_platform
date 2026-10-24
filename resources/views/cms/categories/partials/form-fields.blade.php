@php $p = $prefix; @endphp

<div class="form-grid">
    <div class="form-group">
        <label for="{{ $p }}_name_ar" class="form-label">{{ __('categories.fields.name_ar') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-language"></i>
            <input type="text" id="{{ $p }}_name_ar" name="name_ar" class="form-control" maxlength="255" required dir="rtl"
                   placeholder="{{ __('categories.placeholders.name_ar') }}">
        </div>
        <div class="field-error" data-error-for="name_ar"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_name_en" class="form-label">{{ __('categories.fields.name_en') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-font"></i>
            <input type="text" id="{{ $p }}_name_en" name="name_en" class="form-control" maxlength="255" required dir="ltr"
                   placeholder="{{ __('categories.placeholders.name_en') }}">
        </div>
        <div class="field-error" data-error-for="name_en"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_slug" class="form-label">{{ __('categories.fields.slug') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-link"></i>
            <input type="text" id="{{ $p }}_slug" name="slug" class="form-control" maxlength="255" dir="ltr"
                   placeholder="{{ __('categories.placeholders.slug') }}">
        </div>
        <small class="form-hint">{{ __('categories.hints.slug') }}</small>
        <div class="field-error" data-error-for="slug"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_icon" class="form-label">{{ __('categories.fields.icon') }}</label>
        <div class="icon-field">
            <div class="input-icon">
                <i class="fa-solid fa-icons"></i>
                <input type="text" id="{{ $p }}_icon" name="icon" class="form-control" maxlength="255" dir="ltr"
                       placeholder="{{ __('categories.placeholders.icon') }}" data-icon-input>
            </div>
            <span class="icon-preview" data-icon-preview><i class="fa-solid fa-folder"></i></span>
        </div>
        <small class="form-hint">{{ __('categories.hints.icon') }}</small>
        <div class="field-error" data-error-for="icon"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_parent" class="form-label">{{ __('categories.fields.parent') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-sitemap"></i>
            <select id="{{ $p }}_parent" name="parent_id" class="form-control" data-parent-select></select>
        </div>
        <div class="field-error" data-error-for="parent_id"></div>
    </div>
</div>