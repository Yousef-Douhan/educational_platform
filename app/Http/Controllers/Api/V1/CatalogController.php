<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\CourseDetailResource;
use App\Http\Resources\V1\CourseResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** الكتالوج العام: الدورات المنشورة فقط، ولا يُرسل محتوى الدروس أو روابط الفيديو أبدًا. */
class CatalogController extends Controller
{
    public function __construct(private readonly CatalogQuery $catalog)
    {
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::whereNull('parent_id')->with('children')->orderBy('name_en')->get()
        );
    }

    public function courses(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:191'],
            'level'    => ['nullable', 'in:beginner,intermediate,advanced'],
            'price'    => ['nullable', 'in:free,paid'],
            'sort'     => ['nullable', 'in:'.implode(',', CatalogQuery::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) $request->query('per_page', 12);

        return CourseResource::collection(
            $this->catalog->search($request->query())->paginate($perPage)->withQueryString()
        );
    }

    public function course(Request $request, string $slug): JsonResponse
    {
        $course = $this->catalog->published()
            ->where('courses.slug', $slug)
            ->with([
                'requirements', 'learningOutcomes',
                // أعمدة محددة بالاسم: لا فيديو ولا محتوى
                'sections.lessons' => fn ($l) => $l->where('is_published', true)
                    ->select(['id', 'section_id', 'title_ar', 'title_en', 'content_type', 'duration_minutes', 'is_free_preview', 'sort_order'])
                    ->orderBy('sort_order')->orderBy('id'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => (new CourseDetailResource($course))->withState($this->stateFor($request->user('api'), $course)),
        ]);
    }

    private function stateFor(?User $user, $course): array
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
