<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    // السعر يُثبَّت عند إنشاء الطلب عبر OrderService فقط
    protected $fillable = [];

    protected $casts = ['price' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
