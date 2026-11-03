<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'success', 'failed', 'refunded'];

    /** أقصى حجم لاستجابة البوابة المخزّنة (بايت) */
    public const MAX_RESPONSE_BYTES = 16384;

    /** أي مفتاح يطابق هذا النمط تُحجب قيمته قبل التخزين (لا بيانات بطاقات ولا أسرار) */
    private const SENSITIVE = '/(card|cvv|cvc|pan\b|secret|password|passwd|token|authorization|signature|api[_-]?key)/i';

    // الحالة والمبالغ تتغير عبر الخدمات فقط، لا بالتعبئة الجماعية
    protected $fillable = [];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'succeeded'        => 'boolean',
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * ينظّف استجابة البوابة قبل حفظها: يحجب الحقول الحساسة على أي عمق،
     * ويستبدل الاستجابة الضخمة بعلامة بدل تخزينها.
     */
    public static function sanitize(?array $response): ?array
    {
        if ($response === null || $response === []) {
            return null;
        }

        $clean = self::redact($response);

        if (strlen((string) json_encode($clean)) > self::MAX_RESPONSE_BYTES) {
            return ['truncated' => true];
        }

        return $clean;
    }

    private static function redact(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE, $key)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = self::redact($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('payments.transaction_reference', 'like', $like)
                ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->withTrashed()
                        ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))));
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('payments.status', $status) : $query;
    }

    public function scopeForGateway(Builder $query, ?string $gateway): Builder
    {
        return filled($gateway) ? $query->where('payments.gateway', $gateway) : $query;
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('payments.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payments.created_at', '<=', $to));
    }
}
