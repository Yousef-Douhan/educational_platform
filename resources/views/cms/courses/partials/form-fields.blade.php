@php $p = $prefix; @endphp

<div class="form-grid">
    <div class="form-section"><i class="fa-solid fa-image"></i> {{ __('courses.sections.basic') }}</div>

    <div class="form-group form-group--full">
        <span class="form-label">{{ __('courses.fields.image') }}</span>
        <div class="upload-box">
            <div class="upload-preview" data-image-preview><i class="fa-regular fa-image"></i></div>
            <div class="upload-actions">
                <label for="{{ $p }}_image" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('courses.actions.choose_image') }}
                </label>
                <input type="file" id="{{ $p }}_image" name="image" accept="image/jpeg,image/png,image/webp" data-image-input hidden>
                <small class="form-hint">{{ __('courses.hints.image') }}</small>
                @if ($p === 'edit')
                    <label class="switch-row" data-remove-image-wrap hidden>
                        <span class="switch">
                            <input type="checkbox" name="remove_image" value="1" data-remove-image>
                            <span class="switch__slider"></span>
                        </span>
                        <span>{{ __('courses.fields.remove_image') }}</span>
                    </label>
                @endif
            </div>
        </div>
        <div class="field-error" data-error-for="image"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_title_ar" class="form-label">{{ __('courses.fields.title_ar') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-language"></i>
            <input type="text" id="{{ $p }}_title_ar" name="title_ar" class="form-control" maxlength="255" required dir="rtl"
                   placeholder="{{ __('courses.placeholders.title_ar') }}">
        </div>
        <div class="field-error" data-error-for="title_ar"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_title_en" class="form-label">{{ __('courses.fields.title_en') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-font"></i>
            <input type="text" id="{{ $p }}_title_en" name="title_en" class="form-control" maxlength="255" required dir="ltr"
                   placeholder="{{ __('courses.placeholders.title_en') }}">
        </div>
        <div class="field-error" data-error-for="title_en"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_slug" class="form-label">{{ __('courses.fields.slug') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-link"></i>
            <input type="text" id="{{ $p }}_slug" name="slug" class="form-control" maxlength="255" dir="ltr"
                   placeholder="{{ __('courses.placeholders.slug') }}">
        </div>
        <small class="form-hint">{{ __('courses.hints.slug') }}</small>
        <div class="field-error" data-error-for="slug"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_category" class="form-label">{{ __('courses.fields.category') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-folder-tree"></i>
            <select id="{{ $p }}_category" name="category_id" class="form-control" required data-category-select></select>
        </div>
        <div class="field-error" data-error-for="category_id"></div>
    </div>

    <div class="form-group" data-instructor-wrap>
        <label for="{{ $p }}_instructor" class="form-label">{{ __('courses.fields.instructor') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-chalkboard-user"></i>
            <select id="{{ $p }}_instructor" name="instructor_id" class="form-control" data-instructor-select></select>
        </div>
        <div class="field-error" data-error-for="instructor_id"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_level" class="form-label">{{ __('courses.fields.level') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-signal"></i>
            <select id="{{ $p }}_level" name="level" class="form-control" required data-level-select></select>
        </div>
        <div class="field-error" data-error-for="level"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_status" class="form-label">{{ __('courses.fields.status') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-toggle-on"></i>
            <select id="{{ $p }}_status" name="status" class="form-control" data-status-select></select>
        </div>
        <small class="form-hint" data-status-locked hidden>{{ __('courses.hints.status_locked') }}</small>
        <div class="field-error" data-error-for="status"></div>
    </div>

    <div class="form-section"><i class="fa-solid fa-tags"></i> {{ __('courses.sections.pricing') }}</div>

    <div class="form-group">
        <label for="{{ $p }}_price" class="form-label">{{ __('courses.fields.price') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-coins"></i>
            <input type="number" id="{{ $p }}_price" name="price" class="form-control" min="0" step="0.01" value="0" required dir="ltr">
        </div>
        <small class="form-hint">{{ __('courses.hints.price') }}</small>
        <div class="field-error" data-error-for="price"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_discount" class="form-label">{{ __('courses.fields.discount_price') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-percent"></i>
            <input type="number" id="{{ $p }}_discount" name="discount_price" class="form-control" min="0" step="0.01" dir="ltr">
        </div>
        <div class="field-error" data-error-for="discount_price"></div>
    </div>

    <div class="form-section"><i class="fa-solid fa-user-group"></i> {{ __('courses.sections.team') }}</div>

    <div class="form-group form-group--full">
        <span class="form-label">{{ __('courses.fields.co_instructors') }}</span>
        <div class="chip-options" data-co-instructors></div>
        <small class="form-hint">{{ __('courses.hints.co_instructors') }}</small>
        <div class="field-error" data-error-for="co_instructors"></div>
    </div>

    <div class="form-section"><i class="fa-solid fa-align-left"></i> {{ __('courses.sections.content') }}</div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_description_ar" class="form-label">{{ __('courses.fields.description_ar') }}</label>
        <textarea id="{{ $p }}_description_ar" name="description_ar" rows="3" class="form-control" dir="rtl"></textarea>
        <div class="field-error" data-error-for="description_ar"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_description_en" class="form-label">{{ __('courses.fields.description_en') }}</label>
        <textarea id="{{ $p }}_description_en" name="description_en" rows="3" class="form-control" dir="ltr"></textarea>
        <div class="field-error" data-error-for="description_en"></div>
    </div>

    <div class="form-group form-group--full">
        <div class="rep-head">
            <span class="form-label">{{ __('courses.fields.requirements') }}</span>
            <button type="button" class="btn btn-light btn-sm" data-repeater-add="requirements">
                <i class="fa-solid fa-plus"></i> {{ __('courses.actions.add_row') }}
            </button>
        </div>
        <div class="rep-list" data-repeater="requirements"></div>
        <div class="field-error" data-error-for="requirements"></div>
    </div>

    <div class="form-group form-group--full">
        <div class="rep-head">
            <span class="form-label">{{ __('courses.fields.learning_outcomes') }}</span>
            <button type="button" class="btn btn-light btn-sm" data-repeater-add="learning_outcomes">
                <i class="fa-solid fa-plus"></i> {{ __('courses.actions.add_row') }}
            </button>
        </div>
        <div class="rep-list" data-repeater="learning_outcomes"></div>
        <div class="field-error" data-error-for="learning_outcomes"></div>
    </div>
</div>