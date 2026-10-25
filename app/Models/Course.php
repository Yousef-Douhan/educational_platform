<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    public const LEVELS   = ['beginner', 'intermediate', 'advanced'];
    public const STATUSES = ['draft', 'pending_review', 'published', 'archived'];

    protected $fillable = [
        'instructor_id', 'category_id', 'title_ar', 'title_en', 'slug',
        'description_ar', 'description_en', 'image_url',
        'price', 'discount_price', 'level', 'status',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'discount_price' => 'decimal:2',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    protected $appends = ['image_src', 'is_free', 'final_price'];

    /* ---------- Relations ---------- */

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function coInstructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors', 'course_id', 'instructor_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CourseRequirement::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(CourseLearningOutcome::class);
    }

    /* ---------- Accessors ---------- */

    /** image_url يخزّن مسار الملف على القرص العام، وليس رابطًا كاملًا */
    public function getImageSrcAttribute(): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        return str_starts_with($this->image_url, 'http')
            ? $this->image_url
            : Storage::disk('public')->url($this->image_url);
    }

    public function getIsFreeAttribute(): bool
    {
        return (float) $this->price <= 0;
    }

    public function getFinalPriceAttribute(): string
    {
        $discount = $this->discount_price;

        return ($discount !== null && (float) $discount < (float) $this->price)
            ? (string) $discount
            : (string) $this->price;
    }

    /* ---------- Scopes ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title_ar', 'like', "%{$term}%")
                ->orWhere('title_en', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }

    /** المدير يرى كل الدورات، والمدرّس يرى دوراته ودورات شارك فيها فقط */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('courses.manage-all')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('courses.instructor_id', $user->id)
                ->orWhereHas('coInstructors', fn (Builder $c) => $c->where('users.id', $user->id));
        });
    }

    /* ---------- Helpers ---------- */

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $root = Str::slug($base) ?: 'course';
        $slug = $root;
        $i    = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$root}-{$i}";
            $i++;
        }

        return $slug;
    }


    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseSection::class, 'course_id', 'section_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}