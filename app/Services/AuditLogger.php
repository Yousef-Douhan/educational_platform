<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserProfile;
use DateTimeInterface;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * يسجّل ما يفعله الناس في النظام (من فعل ماذا ومتى ومن أين).
 *
 *  - الأفعال التي لا فاعل لها في سطر الأوامر (المهام المجدولة، البذور) لا تُسجَّل،
 *    فالسجل لأفعال البشر. أما طلبات الويب بلا مستخدم (webhook مثلًا) فتُسجَّل.
 *  - فشل التسجيل لا يكسر العملية الأصلية: يُبلَّغ عنه فقط.
 *  - الحقول الحساسة تُحجب دائمًا ولا تدخل السجل بقيمها.
 */
class AuditLogger
{
    public const EVENTS = ['created', 'updated', 'deleted', 'force_deleted', 'restored', 'login', 'logout', 'login_failed'];

    /** أجزاء أسماء الحقول التي لا تُسجَّل قيمتها أبدًا */
    public const SENSITIVE = [
        'password', 'token', 'secret', 'remember', 'api_key', 'authorization', 'card', 'cvv',
        'gateway_response', 'meeting_url', 'signature', 'payload',
    ];

    public const REDACTED = '[redacted]';

    /** حقول لا قيمة تدقيقية لها */
    private const NOISE = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * النماذج المراقَبة. نماذج عالية الحجم (السلة، التقدم، إجابات الاختبارات) خارج القائمة عمدًا.
     *
     * @return array<class-string, array{key:string, events:array<int,string>, when?:\Closure}>
     */
    public static function tracked(): array
    {
        $all = ['created', 'updated', 'deleted', 'restored'];

        return [
            User::class                => ['key' => 'user', 'events' => $all],
            UserProfile::class         => ['key' => 'user_profile', 'events' => $all],
            Role::class                => ['key' => 'role', 'events' => ['created', 'updated', 'deleted']],
            Permission::class          => ['key' => 'permission', 'events' => ['created', 'updated', 'deleted']],
            Category::class            => ['key' => 'category', 'events' => $all],
            Course::class              => ['key' => 'course', 'events' => $all],
            CourseSection::class       => ['key' => 'course_section', 'events' => ['created', 'updated', 'deleted']],
            Lesson::class              => ['key' => 'lesson', 'events' => $all],
            Enrollment::class          => ['key' => 'enrollment', 'events' => ['created', 'updated']],
            Quiz::class                => ['key' => 'quiz', 'events' => $all],
            Certificate::class         => ['key' => 'certificate', 'events' => ['created', 'updated', 'deleted']],
            CertificateTemplate::class => ['key' => 'certificate_template', 'events' => $all],
            Coupon::class              => ['key' => 'coupon', 'events' => $all],
            Order::class               => ['key' => 'order', 'events' => ['created', 'updated']],
            Payment::class             => ['key' => 'payment', 'events' => ['created', 'updated']],
            LiveSession::class         => ['key' => 'live_session', 'events' => $all],
            // الاختبارات كثيرة؛ يهمّنا فقط إبطال المحاولة
            QuizAttempt::class         => [
                'key'    => 'quiz_attempt',
                'events' => ['updated'],
                'when'   => fn (Model $m) => $m->status === 'voided' && $m->wasChanged('status'),
            ],
        ];
    }

    /** مفاتيح الأنواع التي يمكن الفلترة بها */
    public static function typeKeys(): array
    {
        return array_merge(array_column(self::tracked(), 'key'), ['auth']);
    }

    /* ---------- التسجيل التلقائي ---------- */

    public function register(): void
    {
        foreach (self::tracked() as $class => $cfg) {
            $soft = in_array(SoftDeletes::class, class_uses_recursive($class), true);

            foreach ($cfg['events'] as $event) {
                if ($event === 'restored' && ! $soft) {
                    continue; // الحدث غير موجود في النماذج بلا حذف ناعم
                }

                $class::$event(function (Model $model) use ($cfg, $event) {
                    $this->guarded(fn () => $this->onModelEvent($cfg, $event, $model));
                });
            }
        }

        Event::listen(Login::class, fn (Login $e) => $this->guarded(fn () => $this->record(
            'auth', 'login', null, [], [], null, $e->user instanceof User ? $e->user : null,
        )));

        Event::listen(Logout::class, fn (Logout $e) => $this->guarded(fn () => $this->record(
            'auth', 'logout', null, [], [], null, $e->user instanceof User ? $e->user : null,
        )));

        // يُسجَّل البريد المحاوَل فقط، وكلمة المرور لا تُقرأ أصلًا
        Event::listen(Failed::class, fn (Failed $e) => $this->guarded(fn () => $this->record(
            'auth', 'login_failed', null, [], [],
            'email: ' . mb_substr((string) ($e->credentials['email'] ?? ''), 0, 200),
            $e->user instanceof User ? $e->user : null,
        )));
    }

    private function onModelEvent(array $cfg, string $event, Model $model): void
    {
        if (isset($cfg['when']) && ! ($cfg['when'])($model)) {
            return;
        }

        $old = [];
        $new = [];

        switch ($event) {
            case 'created':
                $new = array_diff_key($model->getAttributes(), array_flip(self::NOISE));
                break;

            case 'updated':
                $changes = array_diff_key($model->getChanges(), array_flip(self::NOISE));
                if ($changes === []) {
                    return; // مثل الاسترجاع: تغيّر deleted_at فقط
                }
                $new = $changes;
                $old = array_intersect_key($model->getOriginal(), $changes);
                break;

            case 'deleted':
                $old = array_diff_key($model->getAttributes(), array_flip(self::NOISE));
                if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                    $event = 'force_deleted';
                }
                break;
        }

        $this->record($cfg['key'], $event, $model, $old, $new);
    }

    /* ---------- الكتابة ---------- */

    /**
     * @return AuditLog|null  null عند التخطي (لا فاعل في سطر الأوامر)
     */
    public function record(
        string $type,
        string $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $description = null,
        ?User $actor = null,
        bool $force = false,
    ): ?AuditLog {
        $actor ??= Auth::user();

        if (! $actor && ! $force && app()->runningInConsole()) {
            return null;
        }

        $request = request();
        $log     = new AuditLog();

        $log->forceFill([
            'user_id'       => $actor?->getKey(),
            'user_name'     => $actor ? mb_substr((string) $actor->name, 0, 255) : null,
            'user_email'    => $actor ? mb_substr((string) $actor->email, 0, 255) : null,
            'action'        => "{$type}.{$event}",
            'subject_type'  => $type,
            'event'         => $event,
            'subject_id'    => $subject?->getKey(),
            'subject_label' => $subject ? $this->label($subject) : null,
            'description'   => $description !== null ? mb_substr($description, 0, 1000) : null,
            'old_values'    => $old === [] ? null : $this->clean($old),
            'new_values'    => $new === [] ? null : $this->clean($new),
            'ip_address'    => $request?->ip(),
            'user_agent'    => $request?->userAgent() ? mb_substr($request->userAgent(), 0, 500) : null,
        ])->save();

        return $log;
    }

    /** يحجب الحساس ويقصّ الطويل ويحوّل كل قيمة إلى نص/رقم/منطقي بسيط */
    public function clean(array $values): array
    {
        $out = [];

        foreach (array_slice($values, 0, 60, true) as $key => $value) {
            $key = (string) $key;

            if ($this->isSensitive($key)) {
                $out[$key] = self::REDACTED;
                continue;
            }

            $out[$key] = match (true) {
                $value === null, is_bool($value), is_int($value), is_float($value) => $value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                is_array($value), is_object($value) => mb_substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 0, 500),
                default => mb_substr((string) $value, 0, 500),
            };
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    /** وصف قصير للكائن من أول حقل مفيد؛ لا يعتمد على علاقات (لا تحميل كسول) */
    private function label(Model $model): string
    {
        foreach (['title_en', 'title', 'name', 'code', 'order_number', 'certificate_number', 'number', 'reference', 'email', 'slug'] as $field) {
            $value = $model->getAttribute($field);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 255);
            }
        }

        return '#' . $model->getKey();
    }

    private function guarded(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e); // التدقيق لا يكسر العملية الأصلية
        }
    }
}
