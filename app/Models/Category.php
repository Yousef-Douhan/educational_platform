<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'icon',
        'parent_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** الاسم حسب لغة الواجهة الحالية */
    public function getNameAttribute(): string
    {
        $primary   = app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
        $secondary = app()->getLocale() === 'ar' ? $this->name_en : $this->name_ar;

        return $primary ?: (string) $secondary;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name_ar', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to));
    }

    /** معرّفات كل الأحفاد (بما فيهم المحذوفون مؤقتًا) لمنع الدورة الدائرية */
    public function descendantIds(): array
    {
        $ids      = [];
        $frontier = [$this->id];

        while ($frontier) {
            $found    = static::withTrashed()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($found, $ids, [$this->id]));
            $ids      = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** slug فريد (يشمل المحذوفات مؤقتًا) */
    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $root = Str::slug($base) ?: 'category';
        $slug = $root;
        $i    = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$root}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
