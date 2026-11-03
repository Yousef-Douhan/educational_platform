<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'refunded'];

    /** الانتقالات المسموحة فقط. failed وcancelled وrefunded حالات نهائية */
    public const TRANSITIONS = [
        'pending' => ['paid', 'failed', 'cancelled'],
        'paid'    => ['refunded'],
    ];

    /** بوابات الدفع اليدوي التي يسجّلها المدير */
    public const MANUAL_GATEWAYS = ['manual', 'bank_transfer', 'cash'];

    /** الطلب المعلّق يُغلق تلقائيًا بعد هذه المدة وتُعاد حصة الكوبون */
    public const PENDING_EXPIRES_AFTER_HOURS = 24;

    // لا شيء يُملأ جماعيًا: المبالغ والحالة تتغير فقط عبر OrderService
    protected $fillable = [];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** CODE بصيغة ORD-XXXXXXXX بأحرف عشوائية آمنة وبلا أحرف ملتبسة */
    public static function generateNumber(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $part     = '';

        for ($i = 0; $i < 8; $i++) {
            $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'ORD-' . $part;
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('orders.order_number', 'like', $like)
                ->orWhere('orders.transaction_reference', 'like', $like)
                ->orWhereHas('user', fn (Builder $u) => $u->withTrashed()
                    ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('orders.status', $status) : $query;
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('orders.created_at', '<=', $to));
    }
}
