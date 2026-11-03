<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية بصلاحيات الـ Controller + نطاق visibleTo
    }

    public function session(): ?LiveSession
    {
        $route = $this->route('live_session');

        return $route instanceof LiveSession ? $route : null;
    }

    public function rules(): array
    {
        $session = $this->session();

        return [
            'course_id' => $session
                ? ['nullable', 'integer']
                : ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'title_ar'         => ['required', 'string', 'min:3', 'max:255'],
            'title_en'         => ['required', 'string', 'min:3', 'max:255'],
            'meeting_url'      => ['required', 'string', 'max:2048', function ($attr, $value, $fail) {
                if (! self::isSafeMeetingUrl((string) $value)) {
                    $fail(__('live_sessions.validation.url_invalid'));
                }
            }],
            'scheduled_at'     => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
        ];
    }

    /** https فقط، بلا بيانات دخول داخل الرابط، وبلا فراغات أو محارف تحكم */
    public static function isSafeMeetingUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        return $parts !== false
            && ($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && str_contains($parts['host'], '.')
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $session = $this->session();

            if ($session) {
                if ($this->filled('course_id') && (int) $this->input('course_id') !== $session->course_id) {
                    $v->errors()->add('course_id', __('live_sessions.validation.course_locked'));
                }
            } else {
                $course = Course::query()->visibleTo($this->user())->find($this->input('course_id'));

                if (! $course) {
                    $v->errors()->add('course_id', __('live_sessions.validation.course_invalid'));
                } elseif (! in_array($course->status, LiveSessionService::SCHEDULABLE_COURSE_STATUSES, true)) {
                    $v->errors()->add('course_id', __('live_sessions.errors.course_unavailable'));
                }
            }

            // الوقت يجب أن يكون مستقبلًا عند الإنشاء أو عند تغييره فقط
            $start   = Carbon::parse($this->input('scheduled_at'));
            $changed = ! $session || ! $start->equalTo($session->scheduled_at);

            if ($changed && $start->lte(now())) {
                $v->errors()->add('scheduled_at', __('live_sessions.validation.future'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'course_id.required'   => __('live_sessions.validation.course_required'),
            'course_id.exists'     => __('live_sessions.validation.course_invalid'),
            'duration_minutes.min' => __('live_sessions.validation.duration'),
            'duration_minutes.max' => __('live_sessions.validation.duration'),
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id'        => __('live_sessions.fields.course'),
            'title_ar'         => __('live_sessions.fields.title_ar'),
            'title_en'         => __('live_sessions.fields.title_en'),
            'meeting_url'      => __('live_sessions.fields.meeting_url'),
            'scheduled_at'     => __('live_sessions.fields.scheduled_at'),
            'duration_minutes' => __('live_sessions.fields.duration_minutes'),
        ];
    }
}
