<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_courses_are_listed(): void
    {
        $pub = Course::factory()->published()->create(['title_en' => 'Visible One', 'title_ar' => 'ظاهرة']);
        Course::factory()->create(['title_en' => 'Hidden Draft', 'title_ar' => 'مسودة']);
        Course::factory()->create(['status' => 'archived']);

        $res = $this->getJson('/api/v1/courses')->assertOk();

        $res->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pub->id)->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertStringNotContainsString('Hidden Draft', $res->getContent());
    }

    public function test_list_filters_by_price_level_and_search(): void
    {
        Course::factory()->published()->create(['title_en' => 'Free Laravel', 'level' => 'beginner', 'price' => 0]);
        Course::factory()->published()->paid(100)->create(['title_en' => 'Paid Vue', 'level' => 'advanced']);

        $this->getJson('/api/v1/courses?price=free')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_free', true);
        $this->getJson('/api/v1/courses?level=advanced')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title_en', 'Paid Vue');
        $this->getJson('/api/v1/courses?q=Laravel')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/courses?level=hacker')->assertUnprocessable();
        $this->getJson('/api/v1/courses?per_page=999')->assertUnprocessable();
    }

    public function test_category_filter_includes_sub_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'prog']);
        $child  = Category::factory()->create(['slug' => 'web', 'parent_id' => $parent->id]);
        $other  = Category::factory()->create(['slug' => 'art']);

        Course::factory()->published()->create(['category_id' => $child->id]);
        Course::factory()->published()->create(['category_id' => $other->id]);

        $this->getJson('/api/v1/courses?category=prog')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/courses?category=nope')->assertJsonCount(0, 'data');
    }

    public function test_detail_never_leaks_lesson_content_or_video_urls(): void
    {
        $course  = Course::factory()->published()->create(['slug' => 'secure-course']);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->video()->create(['section_id' => $section->id, 'is_published' => true, 'duration_minutes' => 12]);
        Lesson::factory()->create(['section_id' => $section->id, 'is_published' => false, 'title_en' => 'Unpublished Secret']);

        $res = $this->getJson('/api/v1/courses/secure-course')->assertOk()
            ->assertJsonPath('data.slug', 'secure-course')
            ->assertJsonPath('data.stats.lessons', 1)
            ->assertJsonPath('data.state.guest', true);

        $body = $res->getContent();
        $this->assertStringNotContainsString('youtube.com', $body);
        $this->assertStringNotContainsString('video_url', $body);
        $this->assertStringNotContainsString('content_ar', $body);
        $this->assertStringNotContainsString('Unpublished Secret', $body);
    }

    public function test_unpublished_course_detail_is_404(): void
    {
        Course::factory()->create(['slug' => 'draft-course']);

        $this->getJson('/api/v1/courses/draft-course')->assertNotFound();
        $this->getJson('/api/v1/courses/does-not-exist')->assertNotFound();
    }

    public function test_categories_are_returned_as_a_tree(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.children');
    }

    public function test_localized_title_follows_accept_language(): void
    {
        Course::factory()->published()->create(['title_ar' => 'دورة عربية', 'title_en' => 'English Course']);

        $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/courses')->assertJsonPath('data.0.title', 'دورة عربية');
        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/courses')->assertJsonPath('data.0.title', 'English Course');
    }
}
