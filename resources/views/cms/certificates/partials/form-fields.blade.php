<div class="form-grid">
    <div class="form-group form-group--full" data-no-courses hidden>
        <div class="alert-box alert-box--info"><i class="fa-solid fa-circle-info"></i><span>{{ __('certificates.ui.no_courses') }}</span></div>
    </div>

    <div class="form-group form-group--full">
        <label for="create_course" class="form-label">{{ __('certificates.fields.course') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-graduation-cap"></i>
            <select id="create_course" name="course_id" class="form-control" data-course-select required></select>
        </div>
        <div class="field-error" data-error-for="course_id"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="create_student_search" class="form-label">{{ __('certificates.fields.student') }} <span class="required">*</span></label>
        <div class="user-picker" data-student-picker>
            <div class="input-icon" data-student-search-wrap>
                <i class="fa-solid fa-user-graduate"></i>
                <input type="search" id="create_student_search" class="form-control" autocomplete="off" disabled
                       placeholder="{{ __('certificates.ui.student_placeholder') }}" data-student-search>
            </div>
            <input type="hidden" name="user_id" data-student-id>
            <div class="picked-user" data-picked-student hidden>
                <span class="avatar avatar--initial" data-picked-initial>?</span>
                <div class="picked-user__info"><strong data-picked-name></strong><small data-picked-email dir="ltr"></small></div>
                <button type="button" class="cur-btn-lite" data-student-clear aria-label="{{ __('certificates.ui.change_student') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="user-results" data-student-results hidden></div>
        </div>
        <small class="form-hint">{{ __('certificates.ui.student_hint') }}</small>
        <div class="field-error" data-error-for="user_id"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="create_template" class="form-label">{{ __('certificates.fields.template') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-certificate"></i>
            <select id="create_template" name="template_id" class="form-control" data-template-select></select>
        </div>
        <div class="field-error" data-error-for="template_id"></div>
    </div>
</div>