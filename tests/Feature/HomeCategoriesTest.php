<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_shows_categories_with_counts_and_sub_categories(): void
    {
        app()->setLocale('en');
        $root = Category::create(['name_ar' => 'برمجة', 'name_en' => 'Programming', 'slug' => 'programming', 'icon' => 'fa-code']);
        $child = Category::create(['name_ar' => 'ويب', 'name_en' => 'WebDev', 'slug' => 'web', 'parent_id' => $root->id]);

        Course::factory()->published()->create(['category_id' => $root->id]);
        Course::factory()->published()->create(['category_id' => $child->id]);
        Course::factory()->create(['category_id' => $child->id]); // مسودة: لا تُحسب

        $this->get(route('student.home'))->assertOk()
            ->assertSee('Programming')->assertSee('WebDev')
            ->assertSee('2 courses')
            ->assertSee('fa-solid fa-code', false);
    }

    public function test_unsafe_icon_value_is_not_rendered(): void
    {
        Category::create(['name_ar' => 'س', 'name_en' => 'Sx', 'slug' => 'sx', 'icon' => '"><script>alert(1)</script>']);

        $this->get(route('student.home'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_empty_category_shows_zero_state(): void
    {
        app()->setLocale('en');
        Category::create(['name_ar' => 'فارغ', 'name_en' => 'EmptyCat', 'slug' => 'empty']);

        $this->get(route('student.home'))->assertOk()->assertSee('EmptyCat')->assertSee('No courses yet');
    }

    public function test_category_seeder_is_idempotent_and_restores_deleted(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $total = Category::count();
        $this->assertGreaterThan(50, $total);
        $this->assertSame(0, Category::whereNull('parent_id')->whereNull('icon')->count());

        // تعديل يدوي لا يُكتب فوقه، وحذف ناعم يُستعاد
        Category::where('slug', 'python')->update(['name_en' => 'My Python']);
        Category::where('slug', 'java')->first()->delete();

        $this->seed(\Database\Seeders\CategorySeeder::class);

        $this->assertSame($total, Category::count());
        $this->assertSame('My Python', Category::where('slug', 'python')->value('name_en'));
        $this->assertNotNull(Category::where('slug', 'java')->first());
        $this->assertSame(Category::where('slug', 'programming')->value('id'), Category::where('slug', 'java')->value('parent_id'));
    }
}
