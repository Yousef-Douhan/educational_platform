<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Quiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['lesson_id', 'title_ar', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed'];

    protected $casts = [
        'pass_mark'          => 'integer',
        'time_limit_minutes' => 'integer',
        'attempts_allowed'   => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');
    }

    public function owningCourse(): Course
    {
        return $this->lesson->section->course;
    }

    /** وجود محاولات يقفل بنية الأسئلة (يتجاهل الجدول إن لم يُنشأ بعد) */
    public function hasAttempts(): bool
    {
        return Schema::hasTable('quiz_attempts')
            && DB::table('quiz_attempts')->where('quiz_id', $this->id)->exists();
    }

    public static function attemptsExistForLesson(int $lessonId): bool
    {
        return Schema::hasTable('quiz_attempts')
            && DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quizzes.lesson_id', $lessonId)
            ->exists();
    }

    public static function attemptsExistForCourse(int $courseId): bool
    {
        return Schema::hasTable('quiz_attempts')
            && DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.section_id')
            ->where('course_sections.course_id', $courseId)
            ->exists();
    }

    /* ---------- Scopes ---------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(fn(Builder $q) => $q->where('title_ar', 'like', $like)->orWhere('title_en', 'like', $like));
    }

    /** المدير يرى الكل، والمدرّس يرى اختبارات دوراته ودورات شارك فيها */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('lesson', fn($l) => $l->withTrashed()
            ->whereHas('section', fn($s) => $s
                ->whereHas('course', fn($c) => $c->withTrashed()->visibleTo($user))));
    }
}
