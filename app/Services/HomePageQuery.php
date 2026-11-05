<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * بيانات الصفحة الرئيسية العامة: أحدث الدورات، الأقسام بعدد دوراتها، المدرّسون، والإحصاءات.
 * كل دالة تُنفّذ عددًا ثابتًا من الاستعلامات (لا استعلام لكل صف) ولا تُعيد أي بيانات خاصة.
 */
class HomePageQuery
{
    public const LATEST_COURSES = 8;
    public const MAX_CATEGORIES = 20;
    public const CHILDREN_SHOWN = 3;
    public const MAX_INSTRUCTORS = 8;

    public function __construct(private readonly CatalogQuery $catalog)
    {
    }

    public function latest(): Collection
    {
        return $this->catalog->published()->orderByDesc('courses.id')->limit(self::LATEST_COURSES)->get();
    }

    /**
     * الأقسام الجذرية مع أبنائها المعروضين، ولكلٍّ courses_total (دورات القسم + أبنائه المنشورة)
     * من استعلام تجميعي واحد بدل استعلام لكل قسم.
     */
    public function categories(): Collection
    {
        $perCategory = $this->publishedCountsByCategory();

        $roots = Category::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name_en')])
            ->orderBy('name_en')->limit(self::MAX_CATEGORIES)->get();

        foreach ($roots as $root) {
            $own      = (int) ($perCategory[$root->id] ?? 0);
            $children = (int) $root->children->sum(fn ($child) => (int) ($perCategory[$child->id] ?? 0));

            $root->setAttribute('courses_total', $own + $children);
            $root->setRelation('children', $root->children->take(self::CHILDREN_SHOWN)->values());
        }

        return $roots;
    }

    /**
     * مدرّسو الدورات المنشورة: الاسم والنبذة والصورة وعدد الدورات فقط (لا بريد ولا هاتف).
     * الأكثر دورات أولًا ثم الاسم، كي لا يحجب الترتيب الأبجدي اللاتيني غيره.
     */
    public function instructors(): Collection
    {
        return User::query()->select(['users.id', 'users.name'])
            ->addSelect(['published_courses' => $this->published()->selectRaw('COUNT(*)')->whereColumn('courses.instructor_id', 'users.id')])
            ->with(['profile' => fn ($q) => $q->select(['id', 'user_id', 'avatar', 'bio'])])
            ->whereIn('users.id', $this->published()->select('instructor_id'))
            ->orderByDesc('published_courses')->orderBy('users.name')
            ->limit(self::MAX_INSTRUCTORS)->get();
    }

    /** @return array{courses:int,instructors:int,students:int} */
    public function stats(): array
    {
        return [
            'courses'     => $this->published()->count(),
            'instructors' => $this->published()->distinct('instructor_id')->count('instructor_id'),
            'students'    => Enrollment::grantingAccess()->distinct('user_id')->count('user_id'),
        ];
    }

    private function published(): Builder
    {
        return Course::query()->where('courses.status', 'published');
    }

    /** @return \Illuminate\Support\Collection<int,int> category_id => عدد الدورات المنشورة */
    private function publishedCountsByCategory()
    {
        return $this->published()->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as c')->groupBy('category_id')->pluck('c', 'category_id');
    }
}
