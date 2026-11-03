<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    public const STATUSES = ['in_progress', 'submitted', 'expired', 'voided'];

    /** سماح بسيط بعد انتهاء الوقت لتأخر الشبكة عند التسليم */
    public const GRACE_SECONDS = 30;

    // الحالة والدرجة تتغير عبر QuizAttemptService فقط
    protected $fillable = [];

    protected $casts = [
        'is_open'       => 'boolean',
        'is_passed'     => 'boolean',
        'score'         => 'decimal:2',
        'points_earned' => 'integer',
        'points_total'  => 'integer',
        'pass_mark'     => 'integer',
        'started_at'    => 'datetime',
        'expires_at'    => 'datetime',
        'submitted_at'  => 'datetime',
        'voided_at'     => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class)->withTrashed();
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    /** انتهى الوقت المحدد (بدون السماح)؟ */
    public function isPastDeadline(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at);
    }

    /** انتهى الوقت والسماح معًا؟ */
    public function isPastGrace(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereHas('user', fn (Builder $u) => $u->withTrashed()
                ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orWhereHas('quiz', fn (Builder $z) => $z->withTrashed()
                    ->where(fn (Builder $w) => $w->where('title_ar', 'like', $like)->orWhere('title_en', 'like', $like)));
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('quiz_attempts.status', $status) : $query;
    }

    /** passed | failed: تستثني الجارية والملغاة */
    public function scopeWithResult(Builder $query, ?string $result): Builder
    {
        return match ($result) {
            'passed' => $query->whereIn('quiz_attempts.status', ['submitted', 'expired'])->where('quiz_attempts.is_passed', true),
            'failed' => $query->whereIn('quiz_attempts.status', ['submitted', 'expired'])->where('quiz_attempts.is_passed', false),
            default  => $query,
        };
    }

    public function scopeForQuiz(Builder $query, ?int $quizId): Builder
    {
        return $quizId ? $query->where('quiz_attempts.quiz_id', $quizId) : $query;
    }

    public function scopeStartedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('quiz_attempts.started_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('quiz_attempts.started_at', '<=', $to));
    }

    /** المدير يرى الكل، والمدرّس يرى محاولات اختبارات دوراته ودورات شارك فيها */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('quiz', fn (Builder $q) => $q->withTrashed()->visibleTo($user));
    }
}
