<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    // الحالة والمراجعة تتغير من ReceiptService فقط
    protected $fillable = [];

    protected $casts = [
        'is_open'     => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('payment_receipts.status', $status) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('reference_number', 'like', $like)
            ->orWhere('sender_name', 'like', $like)
            ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like))
            ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', $like)->orWhere('email', 'like', $like)));
    }
}
