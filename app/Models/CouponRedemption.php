<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** استخدام فعّال لكوبون. يُحذف الصف عند فشل الطلب أو إلغائه فيعود الاستخدام للطالب */
class CouponRedemption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected $casts = ['created_at' => 'datetime'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
