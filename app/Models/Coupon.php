<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES    = ['percentage', 'fixed'];
    public const STATUSES = ['active', 'scheduled', 'expired', 'exhausted', 'disabled'];

    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'max_discount', 'min_order_amount',
        'starts_at', 'expires_at', 'usage_limit', 'per_user_limit', 'is_active',
    ];

    // times_used لا يُملأ جماعيًا أبدًا: يتغير فقط عبر CouponService بتحديث ذرّي
    protected $casts = [
        'discount_value'   => 'decimal:2',
        'max_discount'     => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'usage_limit'      => 'integer',
        'per_user_limit'   => 'integer',
        'times_used'       => 'integer',
        'is_active'        => 'boolean',
    ];

    /** الكود يُخزَّن دائمًا بأحرف كبيرة بلا مسافات، فالمطابقة لا تتأثر بحالة الأحرف */
    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    protected function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', self::normalizeCode($code))->first();
    }

    /* ---------- الحالة ---------- */

    /** الأولوية: معطّل ثم منتهٍ ثم مستنفد ثم لم يبدأ ثم فعّال */
    public function status(?Carbon $now = null): string
    {
        $now ??= now();

        return match (true) {
            ! $this->is_active                                           => 'disabled',
            $this->expires_at !== null && $this->expires_at->lte($now)   => 'expired',
            $this->isExhausted()                                         => 'exhausted',
            $this->starts_at !== null && $this->starts_at->gt($now)      => 'scheduled',
            default                                                      => 'active',
        };
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->times_used >= $this->usage_limit;
    }

    public function isUsable(?Carbon $now = null): bool
    {
        return $this->status($now) === 'active';
    }

    public function hasBeenUsed(): bool
    {
        return $this->times_used > 0;
    }

    /* ---------- حساب الخصم (بالسنتات لتفادي أخطاء الفاصلة العائمة) ---------- */

    /** يعيد قيمة الخصم كنص بمنزلتين، ولا تتجاوز المبلغ نفسه أبدًا */
    public function discountFor(string|int|float $subtotal): string
    {
        $subtotalCents = (int) round(((float) $subtotal) * 100);
        $valueCents    = (int) round(((float) $this->discount_value) * 100);

        if ($subtotalCents <= 0) {
            return '0.00';
        }

        if ($this->discount_type === 'percentage') {
            $cents = (int) round($subtotalCents * $valueCents / 10000);

            if ($this->max_discount !== null) {
                $cents = min($cents, (int) round(((float) $this->max_discount) * 100));
            }
        } else {
            $cents = $valueCents;
        }

        return number_format(min($cents, $subtotalCents) / 100, 2, '.', '');
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where('code', 'like', '%' . addcslashes(strtoupper(trim($term)), '%_\\') . '%');
    }

    /** نفس أولوية status() لكن على مستوى SQL */
    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (! in_array($status, self::STATUSES, true)) {
            return $query;
        }

        $now = now();

        $expired   = fn (Builder $q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now);
        $exhausted = fn (Builder $q) => $q->whereNotNull('usage_limit')->whereColumn('times_used', '>=', 'usage_limit');
        $scheduled = fn (Builder $q) => $q->whereNotNull('starts_at')->where('starts_at', '>', $now);

        return match ($status) {
            'disabled'  => $query->where('is_active', false),
            'expired'   => $query->where('is_active', true)->where($expired),
            'exhausted' => $query->where('is_active', true)->whereNot($expired)->where($exhausted),
            'scheduled' => $query->where('is_active', true)->whereNot($expired)->whereNot($exhausted)->where($scheduled),
            'active'    => $query->where('is_active', true)->whereNot($expired)->whereNot($exhausted)->whereNot($scheduled),
        };
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return in_array($type, self::TYPES, true) ? $query->where('discount_type', $type) : $query;
    }
}
