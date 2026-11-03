<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseProgress extends Model
{
    use HasFactory;

    protected $table = 'course_progress';

    // الأرقام والنسبة تُحسب في ProgressService من الدروس، لا بالتعبئة الجماعية
    protected $fillable = [];

    protected $casts = [
        'completed_lessons_count' => 'integer',
        'total_lessons_count'     => 'integer',
        'progress_percentage'     => 'decimal:2',
        'is_finished'             => 'boolean',
        'finished_at'             => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /* ---------- نطاقات الاستعلام ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereHas('user', fn (Builder $u) => $u->withTrashed()
                ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orWhereHas('course', fn (Builder $c) => $c->withTrashed()
                    ->where(fn (Builder $w) => $w->where('title_ar', 'like', $like)->orWhere('title_en', 'like', $like)));
        });
    }

    /** finished | in_progress */
    public function scopeWithState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'finished'    => $query->where('course_progress.is_finished', true),
            'in_progress' => $query->where('course_progress.is_finished', false),
            default       => $query,
        };
    }

    public function scopeForCourse(Builder $query, ?int $courseId): Builder
    {
        return $courseId ? $query->where('course_progress.course_id', $courseId) : $query;
    }

    /** المدير يرى الكل، والمدرّس يرى تقدم طلاب دوراته ودورات شارك فيها */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('course', fn (Builder $c) => $c->withTrashed()->visibleTo($user));
    }
}
