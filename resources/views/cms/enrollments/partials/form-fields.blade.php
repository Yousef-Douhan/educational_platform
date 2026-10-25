@php $p = $prefix; @endphp

<div class="form-grid">
    @if ($p === 'create')
        <div class="form-group form-group--full">
            <label for="create_user_search" class="form-label">{{ __('enrollments.fields.student') }} <span class="required">*</span></label>
            <div class="user-picker" data-user-picker>
                <div class="input-icon" data-user-search-wrap>
                    <i class="fa-solid fa-user-graduate"></i>
                    <input type="search" id="create_user_search" class="form-control" autocomplete="off"
                           placeholder="{{ __('enrollments.placeholders.student') }}" data-user-search>
                </div>
                <input type="hidden" name="user_id" data-user-id>
                <div class="picked-user" data-picked-user hidden>
                    <span class="avatar avatar--initial" data-picked-initial>?</span>
                    <div class="picked-user__info"><strong data-picked-name></strong><small data-picked-email dir="ltr"></small></div>
                    <button type="button" class="cur-btn-lite" data-user-clear aria-label="{{ __('enrollments.actions.change') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="user-results" data-user-results hidden></div>
            </div>
            <small class="form-hint">{{ __('enrollments.hints.student') }}</small>
            <div class="field-error" data-error-for="user_id"></div>
        </div>

        <div class="form-group form-group--full">
            <label for="create_course" class="form-label">{{ __('enrollments.fields.course') }} <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-graduation-cap"></i>
                <select id="create_course" name="course_id" class="form-control" data-course-select required></select>
            </div>
            <small class="form-hint">{{ __('enrollments.hints.course') }}</small>
            <div class="field-error" data-error-for="course_id"></div>
        </div>
    @else
        <div class="form-group form-group--full">
            <div class="readonly-box">
                <div><small>{{ __('enrollments.fields.student') }}</small><strong data-edit-student>—</strong><em data-edit-email dir="ltr"></em></div>
                <div><small>{{ __('enrollments.fields.course') }}</small><strong data-edit-course>—</strong></div>
            </div>
        </div>

        <div class="form-group">
            <label for="edit_status" class="form-label">{{ __('enrollments.fields.status') }} <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-toggle-on"></i>
                <select id="edit_status" name="status" class="form-control" data-status-select required></select>
            </div>
            <div class="field-error" data-error-for="status"></div>
        </div>
    @endif

    <div class="form-group {{ $p === 'create' ? 'form-group--full' : '' }}">
        <label for="{{ $p }}_expires_at" class="form-label">{{ __('enrollments.fields.expires_at') }}</label>
        <div class="input-icon">
            <i class="fa-regular fa-calendar-xmark"></i>
            <input type="datetime-local" id="{{ $p }}_expires_at" name="expires_at" class="form-control" dir="ltr">
        </div>
        <small class="form-hint">{{ __('enrollments.hints.expires_at') }}</small>
        <div class="field-error" data-error-for="expires_at"></div>
    </div>
</div>