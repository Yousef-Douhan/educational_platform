<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل تدقيق: إضافة فقط. لا يُملأ جماعيًا، ولا يُعدَّل ولا يُحذف عبر الموديل.
 * الكتابة الوحيدة تتم من AuditLogger.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('action', 'like', $like)
                ->orWhere('subject_label', 'like', $like)
                ->orWhere('user_name', 'like', $like)
                ->orWhere('user_email', 'like', $like)
                ->orWhere('ip_address', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return in_array($type, \App\Services\AuditLogger::typeKeys(), true) ? $query->where('subject_type', $type) : $query;
    }

    public function scopeOfEvent(Builder $query, ?string $event): Builder
    {
        return in_array($event, \App\Services\AuditLogger::EVENTS, true) ? $query->where('event', $event) : $query;
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }
}
