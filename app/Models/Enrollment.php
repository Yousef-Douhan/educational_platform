<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'completed', 'expired', 'cancelled'];

    /** الحالات التي تمنح وصولًا (ما لم ينتهِ التاريخ) */
    public const ACCESS_STATUSES = ['active', 'completed'];

    public $timestamps = false; // الجدول فيه enrolled_at فقط

    protected $fillable = ['user_id', 'course_id', 'status', 'enrolled_at', 'expires_at'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    protected $appends = ['has_access'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** حق الوصول يُحسب من الحالة والتاريخ معًا، ولا يعتمد على تشغيل المهمة المجدولة */
    public function grantsAccess(): bool
    {
        return in_array($this->status, self::ACCESS_STATUSES, true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function getHasAccessAttribute(): bool
    {
        return $this->grantsAccess();
    }

    /* ---------- Scopes ---------- */

    public function scopeGrantingAccess(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::ACCESS_STATUSES)
            ->where(fn(Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereHas('user', fn($u) => $u->withTrashed()
                ->where(fn($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orWhereHas('course', fn($c) => $c->withTrashed()
                    ->where(fn($w) => $w->where('title_ar', 'like', $like)->orWhere('title_en', 'like', $like)));
        });
    }

    public function scopeEnrolledBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn($q) => $q->whereDate('enrolled_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('enrolled_at', '<=', $to));
    }

    /** المدير يرى الكل، والمدرّس يرى تسجيلات دوراته ودورات شارك فيها */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('course', fn($c) => $c->withTrashed()->visibleTo($user));
    }
}
