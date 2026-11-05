<?php

namespace Tests\Feature;

use App\Models\StudentScheduleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
    }

    private function payload(array $o = []): array
    {
        return array_merge(['title' => 'Prog 1', 'kind' => 'lecture', 'day' => 0, 'start' => '08:00', 'end' => '10:00', 'room' => 'A1', 'color' => '#0f8f7a'], $o);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));

        return $u;
    }

    public function test_guests_are_blocked(): void
    {
        $this->get(route('student.schedule.index'))->assertRedirect(route('login'));
        $this->postJson(route('student.schedule.store'), $this->payload())->assertUnauthorized();
    }

    public function test_student_page_shows_only_my_items(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        StudentScheduleItem::create(['user_id' => $me->id, 'title' => 'MINE-ITEM', 'kind' => 'lab', 'day' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'color' => '#0369a1']);
        StudentScheduleItem::create(['user_id' => $other->id, 'title' => 'THEIRS-ITEM', 'kind' => 'lab', 'day' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'color' => '#0369a1']);

        $this->actingAs($me)->get(route('student.schedule.index'))->assertOk()
            ->assertSee('MINE-ITEM')->assertDontSee('THEIRS-ITEM');
    }

    public function test_store_update_destroy_and_clear(): void
    {
        $u = User::factory()->create();

        $id = $this->actingAs($u)->postJson(route('student.schedule.store'), $this->payload())
            ->assertCreated()->assertJsonPath('start', '08:00')->json('id');

        $this->putJson(route('student.schedule.update', $id), $this->payload(['title' => 'New', 'day' => 3]))
            ->assertOk()->assertJsonPath('title', 'New')->assertJsonPath('day', 3);

        $this->deleteJson(route('student.schedule.destroy', $id))->assertOk();
        $this->assertDatabaseCount('student_schedule_items', 0);

        $this->postJson(route('student.schedule.store'), $this->payload())->assertCreated();
        $this->postJson(route('student.schedule.store'), $this->payload(['day' => 2]))->assertCreated();
        $this->deleteJson(route('student.schedule.clear'))->assertOk();
        $this->assertDatabaseCount('student_schedule_items', 0);
    }

    public function test_validation_rules(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        foreach ([
            ['end' => '08:00'], ['end' => '07:00'], ['start' => '25:00'], ['start' => '8:00'], ['day' => 7], ['day' => -1],
            ['kind' => 'zzz'], ['color' => 'red'], ['color' => '#12345'], ['title' => ''], ['title' => '<b></b>'], ['title' => str_repeat('a', 101)],
        ] as $bad) {
            $this->postJson(route('student.schedule.store'), $this->payload($bad))->assertStatus(422);
        }
        $this->assertDatabaseCount('student_schedule_items', 0);
    }

    public function test_html_is_stripped_and_overlap_is_allowed(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->postJson(route('student.schedule.store'), $this->payload(['title' => '<script>x</script>Net', 'room' => '<i>R</i>']))
            ->assertCreated()->assertJsonPath('title', 'xNet')->assertJsonPath('room', 'R');
        $this->postJson(route('student.schedule.store'), $this->payload(['start' => '09:00', 'end' => '11:00']))->assertCreated();
    }

    public function test_cannot_touch_someone_elses_item(): void
    {
        $owner = User::factory()->create();
        $item = StudentScheduleItem::create(['user_id' => $owner->id, 'title' => 'X', 'kind' => 'lab', 'day' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'color' => '#0369a1']);

        $this->actingAs(User::factory()->create())
            ->putJson(route('student.schedule.update', $item->id), $this->payload())->assertNotFound();
        $this->deleteJson(route('student.schedule.destroy', $item->id))->assertNotFound();
        $this->deleteJson(route('student.schedule.clear'))->assertOk();
        $this->assertDatabaseCount('student_schedule_items', 1);
    }

    public function test_max_items_per_user(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < StudentScheduleItem::MAX_PER_USER; $i++) {
            StudentScheduleItem::create(['user_id' => $u->id, 'title' => 't', 'kind' => 'lab', 'day' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'color' => '#0369a1']);
        }
        $this->actingAs($u)->postJson(route('student.schedule.store'), $this->payload())->assertStatus(422);
    }

    public function test_cms_schedule_page_is_staff_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('cms.admin.schedule.index'))->assertRedirect(route('student.courses.mine'));
        $this->actingAs($this->staff())->get(route('cms.admin.schedule.index'))->assertOk();
    }
}
