<div class="form-grid">
    <div class="form-group">
        <label for="section_title_ar" class="form-label">{{ __('curriculum.fields.title_ar') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-language"></i>
            <input type="text" id="section_title_ar" name="title_ar" class="form-control" maxlength="255" required dir="rtl">
        </div>
        <div class="field-error" data-error-for="title_ar"></div>
    </div>

    <div class="form-group">
        <label for="section_title_en" class="form-label">{{ __('curriculum.fields.title_en') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-font"></i>
            <input type="text" id="section_title_en" name="title_en" class="form-control" maxlength="255" required dir="ltr">
        </div>
        <div class="field-error" data-error-for="title_en"></div>
    </div>
</div>