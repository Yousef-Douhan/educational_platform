<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * received  = وصل ولم يكتمل (انقطاع أثناء المعالجة): إعادة إرسال البوابة تعالجه من جديد.
 * processed = طُبّق. ignored = نوع لا يهمنا. rejected = لا يرتبط بأي دفعة عندنا.
 * failed    = حدث صحيح لكن تعذّر تطبيقه (دفعة متأخرة، مبلغ مختلف...): قد يكون المال وصل، يحتاج مراجعة.
 */
class WebhookEvent extends Model
{
    use HasFactory;

    public const STATUSES = ['received', 'processed', 'ignored', 'rejected', 'failed'];

    // لا يُملأ من أي طلب: تكتبه WebhookProcessor فقط
    protected $fillable = [];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
