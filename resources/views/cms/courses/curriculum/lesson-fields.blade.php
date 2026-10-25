<div class="form-grid">
    <div class="form-group form-group--full" data-lesson-section-wrap hidden>
        <label for="lesson_section" class="form-label">{{ __('curriculum.ui.section') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-layer-group"></i>
            <select id="lesson_section" name="section_id" class="form-control" data-lesson-section disabled></select>
        </div>
        <div class="field-error" data-error-for="section_id"></div>
    </div>

    <div class="form-group">
        <label for="lesson_title_ar" class="form-label">{{ __('curriculum.fields.title_ar') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-language"></i>
            <input type="text" id="lesson_title_ar" name="title_ar" class="form-control" maxlength="255" required dir="rtl">
        </div>
        <div class="field-error" data-error-for="title_ar"></div>
    </div>

    <div class="form-group">
        <label for="lesson_title_en" class="form-label">{{ __('curriculum.fields.title_en') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-font"></i>
            <input type="text" id="lesson_title_en" name="title_en" class="form-control" maxlength="255" required dir="ltr">
        </div>
        <div class="field-error" data-error-for="title_en"></div>
    </div>

    <div class="form-group">
        <label for="lesson_type" class="form-label">{{ __('curriculum.fields.content_type') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-shapes"></i>
            <select id="lesson_type" name="content_type" class="form-control" data-lesson-type></select>
        </div>
        <div class="field-error" data-error-for="content_type"></div>
    </div>

    <div class="form-group">
        <label for="lesson_duration" class="form-label">{{ __('curriculum.fields.duration_minutes') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-clock"></i>
            <input type="number" id="lesson_duration" name="duration_minutes" class="form-control" min="0" max="6000" step="1" dir="ltr">
        </div>
        <div class="field-error" data-error-for="duration_minutes"></div>
    </div>

    <div class="form-group form-group--full" data-type-hint-wrap hidden>
        <div class="alert-box alert-box--info"><i class="fa-solid fa-circle-info"></i><span data-type-hint></span></div>
    </div>

    {{-- فيديو --}}
    <div class="form-group" data-group="video">
        <label for="lesson_provider" class="form-label">{{ __('curriculum.fields.video_provider') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-film"></i>
            <select id="lesson_provider" name="video_provider" class="form-control" data-lesson-provider></select>
        </div>
        <div class="field-error" data-error-for="video_provider"></div>
    </div>

    <div class="form-group" data-group="video">
        <label for="lesson_video_url" class="form-label">{{ __('curriculum.fields.video_url') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-link"></i>
            <input type="text" id="lesson_video_url" name="video_url" class="form-control" maxlength="2000" dir="ltr" data-lesson-video-url>
        </div>
        <div class="field-error" data-error-for="video_url"></div>
    </div>

    {{-- مقال --}}
    <div class="form-group form-group--full" data-group="article">
        <label for="lesson_content_ar" class="form-label">{{ __('curriculum.fields.content_ar') }} <span class="required">*</span></label>
        <textarea id="lesson_content_ar" name="content_ar" rows="6" class="form-control" dir="rtl"></textarea>
        <div class="field-error" data-error-for="content_ar"></div>
    </div>

    <div class="form-group form-group--full" data-group="article">
        <label for="lesson_content_en" class="form-label">{{ __('curriculum.fields.content_en') }}</label>
        <textarea id="lesson_content_en" name="content_en" rows="5" class="form-control" dir="ltr"></textarea>
        <div class="field-error" data-error-for="content_en"></div>
    </div>

    <div class="form-group">
        <label class="switch-row">
            <span class="switch switch--ok">
                <input type="checkbox" name="is_free_preview" value="1">
                <span class="switch__slider"></span>
            </span>
            <span>{{ __('curriculum.ui.free_preview') }}</span>
        </label>
    </div>

    <div class="form-group">
        <label class="switch-row">
            <span class="switch switch--ok">
                <input type="checkbox" name="is_published" value="1">
                <span class="switch__slider"></span>
            </span>
            <span>{{ __('curriculum.ui.published') }}</span>
        </label>
    </div>

    {{-- المرفقات: تُرفع فور اختيار الملف وتعمل بعد حفظ الدرس --}}
    <div class="form-group form-group--full" data-att-panel>
        <span class="form-label"><i class="fa-solid fa-paperclip"></i> {{ __('curriculum.ui.attachments') }}</span>
        <div class="att-locked" data-att-locked>{{ __('curriculum.ui.attachments_locked') }}</div>
        <div data-att-body hidden>
            <div class="att-list" data-att-list></div>
            <div class="att-upload">
                <label for="lesson_att_file" class="btn btn-light btn-sm" data-att-pick>
                    <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('curriculum.ui.upload') }}
                </label>
                <input type="file" id="lesson_att_file" data-att-file hidden
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.png,.jpg,.jpeg,.webp,.mp3">
                <small class="form-hint">{{ __('curriculum.ui.attachments_hint') }}</small>
            </div>
        </div>
        <div class="field-error" data-error-for="file"></div>
    </div>
</div>