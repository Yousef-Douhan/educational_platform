<div class="form-grid">
    <div class="form-group form-group--full">
        <label for="question_text" class="form-label">{{ __('quizzes.fields.question_text') }} <span class="required">*</span></label>
        <textarea id="question_text" name="question_text" rows="3" class="form-control" maxlength="5000" required></textarea>
        <div class="field-error" data-error-for="question_text"></div>
    </div>

    <div class="form-group">
        <label for="question_type" class="form-label">{{ __('quizzes.fields.question_type') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-shapes"></i>
            <select id="question_type" name="question_type" class="form-control" data-q-type></select>
        </div>
        <div class="field-error" data-error-for="question_type"></div>
    </div>

    <div class="form-group">
        <label for="question_points" class="form-label">{{ __('quizzes.fields.points') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-star"></i>
            <input type="number" id="question_points" name="points" class="form-control" min="1" max="100" step="1" value="1" required dir="ltr">
        </div>
        <div class="field-error" data-error-for="points"></div>
    </div>

    <div class="form-group form-group--full" data-q-lock-note hidden>
        <div class="alert-box alert-box--warning"><i class="fa-solid fa-lock"></i><span>{{ __('quizzes.ui.lock_note') }}</span></div>
    </div>

    {{-- اختيار من متعدد --}}
    <div class="form-group form-group--full" data-section="multiple_choice">
        <div class="rep-head">
            <span class="form-label">{{ __('quizzes.ui.options') }}</span>
            <button type="button" class="btn btn-light btn-sm" data-qb-add="mcq"><i class="fa-solid fa-plus"></i> {{ __('quizzes.ui.add_option') }}</button>
        </div>
        <div class="opt-list" data-mcq-list></div>
        <small class="form-hint">{{ __('quizzes.ui.options_hint') }}</small>
    </div>

    {{-- صح / خطأ --}}
    <div class="form-group form-group--full" data-section="true_false" hidden>
        <span class="form-label">{{ __('quizzes.ui.correct_answer') }}</span>
        <div class="tf-choices">
            <label class="tf-choice"><input type="radio" name="tf_answer" value="true" checked><span>{{ __('quizzes.ui.tf_true') }}</span></label>
            <label class="tf-choice"><input type="radio" name="tf_answer" value="false"><span>{{ __('quizzes.ui.tf_false') }}</span></label>
        </div>
        <div class="field-error" data-error-for="tf_answer"></div>
    </div>

    {{-- نص --}}
    <div class="form-group form-group--full" data-section="text" hidden>
        <div class="rep-head">
            <span class="form-label">{{ __('quizzes.ui.accepted_answers') }}</span>
            <button type="button" class="btn btn-light btn-sm" data-qb-add="text"><i class="fa-solid fa-plus"></i> {{ __('quizzes.ui.add_answer') }}</button>
        </div>
        <div class="opt-list" data-text-list></div>
        <small class="form-hint">{{ __('quizzes.ui.text_hint') }}</small>
    </div>

    <div class="form-group form-group--full"><div class="field-error" data-error-for="options"></div></div>
</div>