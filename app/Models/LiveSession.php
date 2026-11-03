<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class LiveSession extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['scheduled', 'ended', 'cancelled'];
    /** الحالة كما تُعرض: تُحسب من الوقت فلا تعتمد على تشغيل المهمة المجدولة */
    public const PHASES = ['upcoming', 'live', 'ended', 'cancelled'];
    /** يُفتح الرابط للطالب قبل البداية بهذه الدقائق */
    public const JOIN_EARLY_MINUTES = 15;

    // status وما يتبعه (الإلغاء) وends_at وcreated_by تُكتب عبر الخدمة فقط
    protected $fillable = ['course_id', 'title_ar', 'title_en', 'meeting_url', 'scheduled_at', 'duration_minutes'];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'ends_at'          => 'datetime',
        'cancelled_at'     => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // نهاية الجلسة لا تنحرف أبدًا عن البداية والمدة
        static::saving(function (self $s) {
            if ($s->scheduled_at !== null && $s->duration_minutes !== null) {
                $s->ends_at = $s->scheduled_at->copy()->addMinutes((int) $s->duration_minutes);
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /* ---------- الحالة ---------- */

    public function phase(?Carbon $now = null): string
    {
        $now ??= now();

        return match (true) {
            $this->status === 'cancelled'                                  => 'cancelled',
            $this->status === 'ended' || $this->ends_at->lte($now)         => 'ended',
            $this->scheduled_at->lte($now)                                 => 'live',
            default                                                        => 'upcoming',
        };
    }

    /** يمكن التعديل أو الإلغاء ما دامت الجلسة مجدولة ولم تنتهِ */
    public function isOpenForChanges(?Carbon $now = null): bool
    {
        return in_array($this->phase($now), ['upcoming', 'live'], true);
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title_ar', 'like', $like)
                ->orWhere('title_en', 'like', $like)
                ->orWhereHas('course', fn (Builder $c) => $c->withTrashed()
                    ->where(fn (Builder $w) => $w->where('title_ar', 'like', $like)->orWhere('title_en', 'like', $like)));
        });
    }

    /** نفس منطق phase() لكن على مستوى SQL */
    public function scopeWithPhase(Builder $query, ?string $phase): Builder
    {
        if (! in_array($phase, self::PHASES, true)) {
            return $query;
        }

        $now = now();

        return match ($phase) {
            'cancelled' => $query->where('status', 'cancelled'),
            'ended'     => $query->where('status', '!=', 'cancelled')
                ->where(fn (Builder $q) => $q->where('status', 'ended')->orWhere('ends_at', '<=', $now)),
            'live'      => $query->where('status', 'scheduled')->where('scheduled_at', '<=', $now)->where('ends_at', '>', $now),
            'upcoming'  => $query->where('status', 'scheduled')->where('scheduled_at', '>', $now),
        };
    }

    public function scopeForCourse(Builder $query, ?int $courseId): Builder
    {
        return $courseId ? $query->where('course_id', $courseId) : $query;
    }

    /** المدير يرى الكل، والمدرّس يرى جلسات دوراته ودورات شارك فيها فقط */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('course', fn (Builder $c) => $c->withTrashed()->visibleTo($user));
    }

    /** الجلسات المجدولة التي تتقاطع مع الفترة [start, end) لنفس الدورة أو لنفس مدرّسها */
    public function scopeOverlapping(Builder $query, Course $course, Carbon $start, Carbon $end, ?int $ignoreId = null): Builder
    {
        return $query
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where(function (Builder $q) use ($course) {
                $q->where('course_id', $course->id)
                    ->orWhereIn('course_id', Course::withTrashed()->where('instructor_id', $course->instructor_id)->select('id'));
            });
    }
}
