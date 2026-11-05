<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        \Illuminate\Support\Facades\Storage::fake('public'); // لا نكتب صورًا رمزية حقيقية أثناء الاختبار
    }

    public function test_seeds_published_courses_with_content_and_is_idempotent(): void
    {
        $this->seed(CourseSeeder::class);

        $count = Course::count();
        $this->assertGreaterThanOrEqual(30, $count);
        $this->assertSame($count, Course::where('status', 'published')->count());
        $this->assertSame(0, Course::doesntHave('sections')->count());
        $this->assertSame(0, Lesson::where('is_published', false)->count());
        $this->assertSame(8, User::role('instructor')->count());

        // تعديل يدوي لا يُكتب فوقه، ولا تكرار عند إعادة التشغيل
        Course::where('slug', 'python-from-zero')->update(['title_en' => 'Edited']);
        $this->seed(CourseSeeder::class);

        $this->assertSame($count, Course::count());
        $this->assertSame('Edited', Course::where('slug', 'python-from-zero')->value('title_en'));
        $this->assertSame(8, User::role('instructor')->count());
    }

    public function test_each_course_gets_the_instructor_of_its_category_and_home_lists_them(): void
    {
        $this->seed(CourseSeeder::class);

        $this->assertSame(8, User::role('instructor')->count());
        $this->assertSame(0, User::where('email', 'team@madar.local')->count());

        $python = Course::where('slug', 'python-from-zero')->first();
        $this->assertSame('ahmad.khatib@madar.local', $python->instructor->email);
        $this->assertSame('layla.haddad@madar.local', Course::where('slug', 'ui-ux-design')->first()->instructor->email);

        $this->get(route('student.home'))->assertOk()->assertSee('د. أحمد الخطيب')->assertSee('الخط العربي');
        $this->get(route('student.courses.index'))->assertOk();
    }

    public function test_instructors_get_bio_and_avatar_without_overwriting_real_ones(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(\Database\Seeders\InstructorSeeder::class);

        $ahmad = User::where('email', 'ahmad.khatib@madar.local')->first();
        $this->assertNotNull($ahmad->profile->bio);
        $this->assertStringStartsWith('avatars/seeded/', $ahmad->profile->avatar);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($ahmad->profile->avatar);

        $ahmad->profile->update(['avatar' => 'avatars/real.jpg', 'bio' => 'نبذتي']);
        $this->seed(\Database\Seeders\InstructorSeeder::class);

        $this->assertSame('avatars/real.jpg', $ahmad->profile->fresh()->avatar);
        $this->assertSame('نبذتي', $ahmad->profile->fresh()->bio);
        $this->assertSame(8, \App\Models\UserProfile::count());
    }

    public function test_home_instructor_cards_show_photo_and_bio_but_no_private_data(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(CourseSeeder::class);
        User::where('email', 'ahmad.khatib@madar.local')->first()->profile->update(['phone' => '0599123456']);

        $this->get(route('student.home'))->assertOk()
            ->assertSee('avatars/seeded/ahmad.khatib.svg', false)
            ->assertSee('مهندس برمجيات')
            ->assertDontSee('0599123456')
            ->assertDontSee('ahmad.khatib@madar.local');
    }

    public function test_instructor_seeder_is_idempotent_and_accounts_are_not_loginable(): void
    {
        $this->seed(\Database\Seeders\InstructorSeeder::class);
        $this->seed(\Database\Seeders\InstructorSeeder::class);

        $this->assertSame(8, User::role('instructor')->count());

        $this->post(route('login'), ['email' => 'ahmad.khatib@madar.local', 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_previously_seeded_courses_owned_by_someone_else_are_moved(): void
    {
        $other = User::factory()->create();
        $cat = \App\Models\Category::create(['name_ar' => 'برمجة', 'name_en' => 'Programming', 'slug' => 'programming']);
        $seeded = Course::factory()->published()->create(['instructor_id' => $other->id, 'category_id' => $cat->id, 'slug' => 'python-from-zero']);
        $mine = Course::factory()->published()->create(['instructor_id' => $other->id, 'category_id' => $cat->id, 'slug' => 'my-own-course']);

        $this->seed(\Database\Seeders\InstructorSeeder::class);

        $this->assertSame('ahmad.khatib@madar.local', $seeded->fresh()->instructor->email);
        $this->assertSame($other->id, $mine->fresh()->instructor_id);
    }
}
