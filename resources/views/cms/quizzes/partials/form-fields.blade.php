@php $p = $prefix; @endphp

<div class="form-grid">
    @if ($p === 'create')
        <div class="form-group">
            <label for="create_course" class="form-label">{{ __('quizzes.ui.course') }} <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-graduation-cap"></i>
                <select id="create_course" class="form-control" data-course-select></select>
            </div>
        </div>

        <div class="form-group">
            <label for="create_lesson" class="form-label">{{ __('quizzes.fields.lesson') }} <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-chalkboard"></i>
                <select id="create_lesson" name="lesson_id" class="form-control" data-lesson-select disabled></select>
            </div>
            <small class="form-hint">{{ __('quizzes.hints.lesson') }}</small>
            <div class="field-error" data-error-for="lesson_id"></div>
        </div>

        <div class="form-group form-group--full" data-no-lessons hidden>
            <div class="alert-box alert-box--info"><i class="fa-solid fa-circle-info"></i><span>{{ __('quizzes.ui.no_lessons') }}</span></div>
        </div>
    @else
        <div class="form-group form-group--full">
            <div class="readonly-box">
                <div><small>{{ __('quizzes.ui.course') }}</small><strong data-edit-course>—</strong></div>
                <div><small>{{ __('quizzes.fields.lesson') }}</small><strong data-edit-lesson>—</strong></div>
            </div>
        </div>

        <div class="form-group form-group--full" data-attempts-note hidden>
            <div class="alert-box alert-box--info"><i class="fa-solid fa-circle-info"></i><span>{{ __('quizzes.ui.attempts_note') }}</span></div>
        </div>
    @endif

    <div class="form-group">
        <label for="{{ $p }}_title_ar" class="form-label">{{ __('quizzes.fields.title_ar') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-language"></i>
            <input type="text" id="{{ $p }}_title_ar" name="title_ar" class="form-control" maxlength="255" required dir="rtl"
                   placeholder="{{ __('quizzes.placeholders.title_ar') }}">
        </div>
        <div class="field-error" data-error-for="title_ar"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_title_en" class="form-label">{{ __('quizzes.fields.title_en') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-font"></i>
            <input type="text" id="{{ $p }}_title_en" name="title_en" class="form-control" maxlength="255" required dir="ltr"
                   placeholder="{{ __('quizzes.placeholders.title_en') }}">
        </div>
        <div class="field-error" data-error-for="title_en"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_pass_mark" class="form-label">{{ __('quizzes.fields.pass_mark') }} (%) <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-percent"></i>
            <input type="number" id="{{ $p }}_pass_mark" name="pass_mark" class="form-control" min="0" max="100" step="1" value="50" required dir="ltr">
        </div>
        <small class="form-hint">{{ __('quizzes.hints.pass_mark') }}</small>
        <div class="field-error" data-error-for="pass_mark"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_time_limit" class="form-label">{{ __('quizzes.fields.time_limit') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-stopwatch"></i>
            <input type="number" id="{{ $p }}_time_limit" name="time_limit_minutes" class="form-control" min="0" max="1440" step="1" value="0" required dir="ltr">
        </div>
        <small class="form-hint">{{ __('quizzes.hints.time_limit') }}</small>
        <div class="field-error" data-error-for="time_limit_minutes"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_attempts" class="form-label">{{ __('quizzes.fields.attempts') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-rotate"></i>
            <input type="number" id="{{ $p }}_attempts" name="attempts_allowed" class="form-control" min="0" max="100" step="1" value="3" required dir="ltr">
        </div>
        <small class="form-hint">{{ __('quizzes.hints.attempts') }}</small>
        <div class="field-error" data-error-for="attempts_allowed"></div>
    </div>
</div>