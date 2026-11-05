<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * 8 مدرّسين تجريبيين (دور instructor)، لكلٍّ تخصّص من التصنيفات الرئيسية.
 * حساباتهم بكلمات مرور عشوائية لا يمكن الدخول بها (لا ثغرة)؛ يمكنك تعيين كلمة مرور لأي منهم من لوحة التحكم.
 * آمن للتشغيل المتكرر. كما ينقل دورات الـ seeder المنشأة سابقًا باسم مدرّس آخر إلى مدرّس تخصّص تصنيفها.
 *
 *   php artisan db:seed --class=InstructorSeeder
 */
class InstructorSeeder extends Seeder
{
    /** email => [الاسم، التصنيفات الرئيسية التي يدرّسها] */
    public const INSTRUCTORS = [
        'ahmad.khatib@madar.local' => ['د. أحمد الخطيب', ['programming', 'data-ai']],
        'layla.haddad@madar.local' => ['م. ليلى حداد', ['design', 'photography-media']],
        'samer.najjar@madar.local' => ['م. سامر النجار', ['cybersecurity', 'networks-cloud']],
        'huda.qasem@madar.local' => ['د. هدى القاسم', ['business', 'marketing', 'finance-accounting']],
        'khaled.omari@madar.local' => ['أ. خالد العمري', ['languages', 'arts-hobbies']],
        'rana.sharif@madar.local' => ['د. رنا الشريف', ['science', 'mathematics', 'engineering']],
        'yousef.masri@madar.local' => ['د. يوسف المصري', ['health-medicine', 'personal-development', 'education-teaching']],
        'mona.saadi@madar.local' => ['أ. منى السعدي', ['law-humanities', 'religion', 'technical-vocational']],
    ];

    /** email => [نبذة قصيرة، لونا خلفية الصورة الرمزية] */
    private const PROFILES = [
        'ahmad.khatib@madar.local' => ['مهندس برمجيات ومدرّب في تطوير الويب وعلوم البيانات والذكاء الاصطناعي.', ['#6366f1', '#8b5cf6']],
        'layla.haddad@madar.local' => ['مصمّمة واجهات ومصوّرة، تعلّم التصميم الجرافيكي والمونتاج وصناعة المحتوى المرئي.', ['#ec4899', '#f43f5e']],
        'samer.najjar@madar.local' => ['مختص في أمن المعلومات والشبكات والحوسبة السحابية بخبرة ميدانية.', ['#0ea5e9', '#06b6d4']],
        'huda.qasem@madar.local' => ['خبيرة أعمال وتسويق ومحاسبة، تساعدك على بناء مشروعك وإدارة أمواله.', ['#f59e0b', '#f97316']],
        'khaled.omari@madar.local' => ['مدرّس لغات وفنون، يجمع بين الإنجليزية والعربية والخط والموسيقى.', ['#10b981', '#14b8a6']],
        'rana.sharif@madar.local' => ['أستاذة علوم ورياضيات وهندسة، تبسّط المفاهيم المعقّدة بأسلوب عملي.', ['#8b5cf6', '#d946ef']],
        'yousef.masri@madar.local' => ['طبيب ومدرّب تطوير ذاتي، يهتم بالصحة والمهارات الشخصية وأساليب التعليم.', ['#14b8a6', '#0ea5e9']],
        'mona.saadi@madar.local' => ['باحثة في القانون والعلوم الإنسانية والشرعية، ومدرّبة مهارات مهنية.', ['#f43f5e', '#f59e0b']],
    ];

    public function run(): void
    {
        Role::findOrCreate('instructor', 'web');

        foreach (self::INSTRUCTORS as $email => [$name]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make(Str::random(40)), 'status' => 'active', 'email_verified_at' => now()],
            );

            if (! $user->hasRole('instructor')) {
                $user->assignRole('instructor');
            }

            $this->profile($user, $email);
        }

        $this->moveLegacyCourses();
    }

    /**
     * ملف المدرّس: نبذة وصورة رمزية SVG مرسومة (ليست صورة شخص حقيقي).
     * لا يكتب فوق نبذة أو صورة حقيقية رفعتها أنت من لوحة التحكم.
     */
    private function profile(User $user, string $email): void
    {
        [$bio, $hues] = self::PROFILES[$email];
        $path = 'avatars/seeded/'.Str::before($email, '@').'.svg';
        Storage::disk('public')->put($path, $this->avatarSvg($hues[0], $hues[1]));

        $profile = UserProfile::withTrashed()->where('user_id', $user->id)->first();

        if (! $profile) {
            UserProfile::create(['user_id' => $user->id, 'bio' => $bio, 'avatar' => $path]);

            return;
        }

        if ($profile->trashed()) {
            $profile->restore();
        }

        $profile->fill(array_filter([
            'bio' => blank($profile->bio) ? $bio : null,
            'avatar' => blank($profile->avatar) ? $path : null,
        ]))->save();
    }

    private function avatarSvg(string $a, string $b): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient></defs>'
            .'<rect width="200" height="200" fill="url(#g)"/>'
            .'<circle cx="164" cy="34" r="52" fill="#fff" fill-opacity=".10"/><circle cx="26" cy="176" r="40" fill="#fff" fill-opacity=".08"/>'
            .'<circle cx="100" cy="78" r="36" fill="#fff" fill-opacity=".94"/>'
            .'<path d="M28 200c0-40 32-66 72-66s72 26 72 66z" fill="#fff" fill-opacity=".94"/></svg>';
    }

    /** البريد الخاص بمدرّس تصنيفٍ رئيسي معيّن (أو null) */
    public static function emailForRoot(string $rootSlug): ?string
    {
        foreach (self::INSTRUCTORS as $email => [, $roots]) {
            if (in_array($rootSlug, $roots, true)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * دورات الـ seeder التي أُنشئت سابقًا باسم مدرّس آخر (حساب "فريق مدار" أو أول مدرّس موجود) تُنقل
     * إلى مدرّس تخصّص تصنيفها. تُعرَف بالـslug فقط، فلا تُمَسّ دوراتك الخاصة ولا الدورات المسندة أصلًا لمدرّسي الـseeder.
     */
    private function moveLegacyCourses(): void
    {
        $byEmail = User::whereIn('email', array_keys(self::INSTRUCTORS))->pluck('id', 'email');
        $seeded = $byEmail->values()->all();
        $slugs = array_column(CourseSeeder::COURSES, 0);

        Course::whereIn('slug', $slugs)->whereNotIn('instructor_id', $seeded)->with('category')->get()
            ->each(function (Course $course) use ($byEmail) {
                $cat = $course->category;
                $root = $cat?->parent_id ? Category::withTrashed()->find($cat->parent_id) : $cat;
                $email = $root ? self::emailForRoot($root->slug) : null;

                if ($email && isset($byEmail[$email])) {
                    $course->update(['instructor_id' => $byEmail[$email]]);
                }
            });
    }
}
