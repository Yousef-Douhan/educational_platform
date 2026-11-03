<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * نموذج الشاشة الإدارية (قراءة). الإنشاء يتم فقط عبر قناة الإشعارات في Notifier،
 * ولا يوجد أي مسار HTTP يملأ هذا النموذج.
 */
class UserNotification extends DatabaseNotification
{
    use HasFactory;

    /** المقروء يُحذف بعد هذه المدة، وغير المقروء بعد مدة أطول */
    public const READ_RETENTION_DAYS   = 90;
    public const UNREAD_RETENTION_DAYS = 365;

    public function notifiable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /* ---------- نطاقات الاستعلام ---------- */

    /** يبحث باسم المستلم أو بريده أو نوع الإشعار */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('type', 'like', $like)
                ->orWhere(function (Builder $r) use ($like) {
                    $r->where('notifiable_type', User::class)
                        ->whereIn('notifiable_id', User::withTrashed()
                            ->where(fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                            ->select('id'));
                });
        });
    }

    public function scopeOfKind(Builder $query, ?string $kind): Builder
    {
        return in_array($kind, \App\Notifications\PlatformNotification::KINDS, true) ? $query->where('type', $kind) : $query;
    }

    public function scopeWithReadState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'read'   => $query->whereNotNull('read_at'),
            'unread' => $query->whereNull('read_at'),
            default  => $query,
        };
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }

    /** المقروء الأقدم من مدة الاحتفاظ، وغير المقروء الأقدم من مدتها الأطول */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(fn (Builder $r) => $r->whereNotNull('read_at')
                ->where('created_at', '<', now()->subDays(self::READ_RETENTION_DAYS)))
                ->orWhere(fn (Builder $r) => $r->whereNull('read_at')
                    ->where('created_at', '<', now()->subDays(self::UNREAD_RETENTION_DAYS)));
        });
    }
}
