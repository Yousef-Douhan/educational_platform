@php $p = $prefix; @endphp

<div class="form-grid">
    @if ($p === 'create')
        <div class="form-group form-group--full">
            <label for="create_course" class="form-label">{{ __('live_sessions.fields.course') }} <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-graduation-cap"></i>
                <select id="create_course" name="course_id" class="form-control" data-course-select required></select>
            </div>
            <small class="form-hint">{{ __('live_sessions.hints.course') }}</small>
            <div class="field-error" data-error-for="course_id"></div>
        </div>
    @else
        <div class="form-group form-group--full">
            <div class="readonly-box">
                <div><small>{{ __('live_sessions.fields.course') }}</small><strong data-edit-course>—</strong></div>
            </div>
            <small class="form-hint">{{ __('live_sessions.hints.course_locked') }}</small>
        </div>
    @endif

    <div class="form-group">
        <label for="{{ $p }}_title_ar" class="form-label">{{ __('live_sessions.fields.title_ar') }} <span class="required">*</span></label>
        <input type="text" id="{{ $p }}_title_ar" name="title_ar" class="form-control" maxlength="255" required dir="rtl" autocomplete="off">
        <div class="field-error" data-error-for="title_ar"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_title_en" class="form-label">{{ __('live_sessions.fields.title_en') }} <span class="required">*</span></label>
        <input type="text" id="{{ $p }}_title_en" name="title_en" class="form-control" maxlength="255" required dir="ltr" autocomplete="off">
        <div class="field-error" data-error-for="title_en"></div>
    </div>

    <div class="form-group form-group--full">
        <label for="{{ $p }}_meeting_url" class="form-label">{{ __('live_sessions.fields.meeting_url') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-link"></i>
            <input type="url" id="{{ $p }}_meeting_url" name="meeting_url" class="form-control" maxlength="2048" required
                   dir="ltr" autocomplete="off" spellcheck="false" placeholder="https://meet.example.com/room">
        </div>
        <small class="form-hint">{{ __('live_sessions.hints.meeting_url') }}</small>
        <div class="field-error" data-error-for="meeting_url"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_scheduled_at" class="form-label">{{ __('live_sessions.fields.scheduled_at') }} <span class="required">*</span></label>
        <input type="datetime-local" id="{{ $p }}_scheduled_at" name="scheduled_at" class="form-control" required dir="ltr">
        <small class="form-hint">{{ __('live_sessions.hints.scheduled_at') }}</small>
        <div class="field-error" data-error-for="scheduled_at"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_duration_minutes" class="form-label">{{ __('live_sessions.fields.duration_minutes') }} <span class="required">*</span></label>
        <input type="number" id="{{ $p }}_duration_minutes" name="duration_minutes" class="form-control" required
               min="5" max="720" step="5" value="60" dir="ltr" inputmode="numeric">
        <small class="form-hint">{{ __('live_sessions.hints.duration') }}</small>
        <div class="field-error" data-error-for="duration_minutes"></div>
    </div>
</div>
