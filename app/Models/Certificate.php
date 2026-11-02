<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    public const CREATED_AT = null; // وقت الإنشاء هو issued_at

    protected $fillable = [
        'user_id',
        'course_id',
        'template_id',
        'certificate_code',
        'student_name',
        'course_title_ar',
        'course_title_en',
        'issued_at',
        'issued_by',
        'revoked_at',
        'revoked_reason',
        'pdf_url',
    ];

    protected $casts = [
        'issued_at'  => 'datetime',
        'revoked_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id')->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function getIsRevokedAttribute(): bool
    {
        return $this->revoked_at !== null;
    }

    /** CERT-XXXX-XXXX-XXXX بأحرف عشوائية آمنة وبلا أحرف ملتبسة (O وI و0 و1) */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $part = '';
                for ($i = 0; $i < 4; $i++) {
                    $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $groups[] = $part;
            }
            $code = 'CERT-' . implode('-', $groups);
        } while (static::where('certificate_code', $code)->exists());

        return $code;
    }

    /* ---------- Scopes ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(fn(Builder $q) => $q
            ->where('certificate_code', 'like', $like)
            ->orWhere('student_name', 'like', $like)
            ->orWhere('course_title_ar', 'like', $like)
            ->orWhere('course_title_en', 'like', $like));
    }

    public function scopeIssuedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn($q) => $q->whereDate('issued_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('issued_at', '<=', $to));
    }

    /** المدير يرى الكل، والمدرّس يرى شهادات دوراته ودورات شارك فيها */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('course', fn($c) => $c->withTrashed()->visibleTo($user));
    }
}
