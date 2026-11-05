<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CatalogQuery;
use App\Services\HomePageQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** الصفحات العامة: لا تكشف إلا الدورات المنشورة، ولا تُرسل محتوى الدروس (فيديو/نص) أبدًا */
class CatalogController extends Controller
{
    public function __construct(private readonly CatalogQuery $catalog)
    {
    }

    public function home(HomePageQuery $home): View
    {
        return view('student.home', [
            'latest'      => $home->latest(),
            'categories'  => $home->categories(),
            'instructors' => $home->instructors(),
            'stats'       => $home->stats(),
        ]);
    }

    public function index(Request $request): View
    {
        $sort = CatalogQuery::sort($request->query('sort'));
        $term = is_string($request->query('q')) ? $request->query('q') : null;

        $courses = $this->catalog->search($request->query())->paginate(12)->withQueryString();

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
