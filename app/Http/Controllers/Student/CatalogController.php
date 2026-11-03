<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** الصفحات العامة: لا تكشف إلا الدورات المنشورة، ولا تُرسل محتوى الدروس (فيديو/نص) أبدًا */
class CatalogController extends Controller
{
    private const SORTS = ['newest', 'price_asc', 'price_desc'];

    private const EFFECTIVE_PRICE = 'CASE WHEN courses.discount_price IS NOT NULL AND courses.discount_price < courses.price THEN courses.discount_price ELSE courses.price END';

    public function home(): View
    {
        $published = Course::query()->where('status', 'published');

        return view('student.home', [
            'latest'      => $this->published()->orderByDesc('courses.id')->limit(8)->get(),
            'categories'  => Category::whereNull('parent_id')->orderBy('name_en')->limit(12)->get(),
            // الاسم فقط: لا بريد ولا أي بيانات خاصة للمدرّسين
            'instructors' => User::query()->select(['id', 'name'])
                ->whereIn('id', (clone $published)->select('instructor_id'))->orderBy('name')->limit(8)->get(),
            'stats'       => [
                'courses'     => (clone $published)->count(),
                'instructors' => (clone $published)->distinct('instructor_id')->count('instructor_id'),
                'students'    => Enrollment::grantingAccess()->distinct('user_id')->count('user_id'),
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest';
        $term = is_string($request->query('q')) ? $request->query('q') : null;

        $query = $this->published()->search($term);

        if (is_string($slug = $request->query('category')) && $slug !== '') {
            $category = Category::where('slug', $slug)->first();
            $ids      = $category ? Category::where('parent_id', $category->id)->pluck('id')->push($category->id) : collect([0]);
            $query->whereIn('courses.category_id', $ids);
        }

        if (in_array($request->query('level'), Course::LEVELS, true)) {
            $query->where('courses.level', $request->query('level'));
        }

        match ($request->query('price')) {
            'free'  => $query->where('courses.price', '<=', 0),
            'paid'  => $query->where('courses.price', '>', 0),
            default => null,
        };

        match ($sort) {
            'price_asc'  => $query->orderByRaw(self::EFFECTIVE_PRICE . ' asc'),
            'price_desc' => $query->orderByRaw(self::EFFECTIVE_PRICE . ' desc'),
            default      => null,
        };

        $courses = $query->orderByDesc('courses.id')->paginate(12)->withQueryString();

        return view('student.courses.index', [
            'courses'    => $courses,
            'categories' => Category::whereNull('parent_id')->orderBy('name_en')->get(),
            'filters'    => [
                'q' => $term, 'category' => $request->query('category'), 'level' => $request->query('level'),
                'price' => $request->query('price'), 'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $course = Course::query()
            ->where('slug', $slug)->where('status', 'published')
            ->with(['category', 'instructor:id,name', 'requirements', 'learningOutcomes'])
            ->firstOrFail();

        // أعمدة محددة بالاسم: رابط الفيديو والمحتوى لا يغادران الخادم في هذه الصفحة
        $sections = $course->sections()->with(['lessons' => fn ($l) => $l
            ->where('is_published', true)
            ->select(['id', 'section_id', 'title_ar', 'title_en', 'content_type', 'duration_minutes', 'is_free_preview', 'sort_order'])
        ])->get();

        $lessons = $sections->flatMap->lessons;

        return view('student.courses.show', [
            'course'   => $course,
            'sections' => $sections,
            'stats'    => ['lessons' => $lessons->count(), 'minutes' => (int) $lessons->sum('duration_minutes')],
            'state'    => $this->stateFor($request->user(), $course),
        ]);
    }

    /* ---------- داخلي ---------- */

    private function published(): BuilderContract
    {
        return Course::query()
            ->where('courses.status', 'published')
            ->with(['category', 'instructor:id,name'])
            ->withCount(['lessons as lessons_count' => fn ($l) => $l->where('lessons.is_published', true)]);
    }

    private function stateFor(?User $user, Course $course): array
    {
        if ($user === null) {
            return ['guest' => true, 'enrolled' => false, 'in_cart' => false, 'teaches' => false];
        }

        return [
            'guest'    => false,
            'enrolled' => Enrollment::grantingAccess()->where(['user_id' => $user->id, 'course_id' => $course->id])->exists(),
            'in_cart'  => CartItem::where('course_id', $course->id)
                ->whereIn('cart_id', Cart::where('user_id', $user->id)->select('id'))->exists(),
            'teaches'  => $course->instructor_id === $user->id || $course->coInstructors()->where('users.id', $user->id)->exists(),
        ];
    }
}
