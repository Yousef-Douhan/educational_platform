<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    public $timestamps = false; // الجدول فيه created_at فقط (يملؤه الجدول)

    protected $fillable = ['cart_id', 'course_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** الدورة قد تُحذف مؤقتًا بعد إضافتها، فنقرؤها مع المحذوفات ونحكم عليها في CartService */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
