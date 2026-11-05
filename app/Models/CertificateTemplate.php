<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'design_path', 'is_default' , 'layout'];

    protected $casts = [
        'is_default' => 'boolean',
        'layout' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['design_url'];

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    public function getDesignUrlAttribute(): ?string
    {
        return $this->design_path ? Storage::disk('public')->url($this->design_path) : null;
    }

    /** يجعله القالب الافتراضي الوحيد داخل معاملة */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()->where('is_default', true)->whereKeyNot($this->id)->update(['is_default' => false]);
            $this->update(['is_default' => true]);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where('name', 'like', '%' . addcslashes(trim($term), '%_\\') . '%');
    }


    public const LAYOUT_DEFAULTS = [
        'area_left' => 0,
        'area_width' => 297,
        'middle_top' => 77,
        'qr_top' => 142,
        'hide_header' => false,
        'hide_date' => false,
        'hide_signature' => false,
        'hide_qr' => false,
        'text_color' => '#111827',
        'accent_color' => '#1e1b4b',
    ];


    public function resolvedLayout(): array
    {
        return array_merge(self::LAYOUT_DEFAULTS, array_intersect_key($this->layout ?? [], self::LAYOUT_DEFAULTS));
    }
}
