<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;

/**
 * استعلام الكتالوج العام (الدورات المنشورة فقط) المشترك بين واجهة الويب والـAPI:
 * بحث، فئة (مع فروعها)، مستوى، سعر، وترتيب. المدخلات مفلترة بقوائم بيضاء.
 */
class CatalogQuery
{
    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    private const EFFECTIVE_PRICE = 'CASE WHEN courses.discount_price IS NOT NULL AND courses.discount_price < courses.price THEN courses.discount_price ELSE courses.price END';

    /** @param array{q?:mixed,category?:mixed,level?:mixed,price?:mixed,sort?:mixed} $f */
    public function search(array $f): Builder
    {
        $term = is_string($f['q'] ?? null) ? $f['q'] : null;

        $query = $this->published()->search($term);

        if (is_string($slug = $f['category'] ?? null) && $slug !== '') {
            $category = Category::where('slug', $slug)->first();
            $ids      = $category ? Category::where('parent_id', $category->id)->pluck('id')->push($category->id) : collect([0]);
            $query->whereIn('courses.category_id', $ids);
        }

        if (in_array($f['level'] ?? null, Course::LEVELS, true)) {
            $query->where('courses.level', $f['level']);
        }

        match ($f['price'] ?? null) {
            'free'  => $query->where('courses.price', '<=', 0),
            'paid'  => $query->where('courses.price', '>', 0),
            default => null,
        };

        match (self::sort($f['sort'] ?? null)) {
            'price_asc'  => $query->orderByRaw(self::EFFECTIVE_PRICE.' asc'),
            'price_desc' => $query->orderByRaw(self::EFFECTIVE_PRICE.' desc'),
            default      => null,
        };

        return $query->orderByDesc('courses.id');
    }

    public static function sort(mixed $sort): string
    {
        return in_array($sort, self::SORTS, true) ? $sort : 'newest';
    }

    /** المنشورة فقط، مع ما يلزم للعرض (تحميل مسبق: التحميل الكسول ممنوع في المشروع) */
    public function published(): Builder
    {
        return Course::query()
            ->where('courses.status', 'published')
            ->with(['category', 'instructor:id,name'])
            ->withCount(['lessons as lessons_count' => fn ($l) => $l->where('lessons.is_published', true)]);
    }
}
