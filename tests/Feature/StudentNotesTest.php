<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentBoardPage;
use App\Models\StudentNote;
use App\Models\User;
use App\Support\BoardSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
    }

    private function enrolled(User $u, string $status = 'active', ?string $title = null): Course
    {
        // عنوان ASCII: الصفحة تضمّن البيانات بـJSON فتُهرَّب الحروف العربية \uXXXX
        $c = Course::factory()->published()->create($title ? ['title_ar' => $title, 'title_en' => $title] : []);
        Enrollment::factory()->create(['user_id' => $u->id, 'course_id' => $c->id, 'status' => $status]);

        return $c;
    }

    private function note(User $u, array $o = []): StudentNote
    {
        return StudentNote::create(array_merge(['user_id' => $u->id, 'title' => 'T', 'body' => 'B'], $o));
    }

    private function page(User $u, array $o = []): StudentBoardPage
    {
        return StudentBoardPage::create(array_merge(['user_id' => $u->id, 'name' => 'P', 'position' => 1, 'objects' => '[]'], $o));
    }

    /* ---------- الوصول ---------- */

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('student.notes.index'))->assertRedirect(route('login'));
        $this->postJson(route('student.notes.store'), ['title' => 'x'])->assertUnauthorized();
        $this->postJson(route('student.board.store'))->assertUnauthorized();
    }

    public function test_index_lists_only_my_courses_and_my_notes(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->enrolled($me, 'active', 'ZZ-mine-course');
        $theirs = $this->enrolled($other, 'active', 'ZZ-theirs-course');
        $this->note($me, ['title' => 'my-secret-note', 'course_id' => $mine->id]);
        $this->note($other, ['title' => 'other-private-note', 'course_id' => $theirs->id]);

        $res = $this->actingAs($me)->get(route('student.notes.index'))->assertOk();
        $res->assertSee('my-secret-note')->assertDontSee('other-private-note');
        $res->assertSee('ZZ-mine-course')->assertDontSee('ZZ-theirs-course');
    }

    /* ---------- الملاحظات ---------- */

    public function test_create_a_general_note_and_one_in_an_enrolled_course(): void
    {
        $me = User::factory()->create();
        $course = $this->enrolled($me);

        $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => '  عام  '])
            ->assertCreated()->assertJsonPath('title', 'عام')->assertJsonPath('course_id', null);

        $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => 'c', 'course_id' => $course->id])
            ->assertCreated()->assertJsonPath('course_id', $course->id);

        $this->assertSame(2, StudentNote::where('user_id', $me->id)->count());
    }

    public function test_cannot_file_a_note_under_a_course_i_am_not_enrolled_in(): void
    {
        $me = User::factory()->create();
        $foreign = Course::factory()->published()->create();
        $cancelled = $this->enrolled($me, 'cancelled');

        foreach ([$foreign->id, $cancelled->id, 999999] as $id) {
            $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => 'x', 'course_id' => $id])
                ->assertStatus(422)->assertJsonValidationErrors('course_id');
        }
        $this->assertSame(0, StudentNote::count());
    }

    public function test_title_is_required_and_body_is_capped(): void
    {
        $me = User::factory()->create();
        $note = $this->note($me);

        $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => ''])->assertStatus(422);
        $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => str_repeat('a', 151)])->assertStatus(422);
        $this->actingAs($me)->putJson(route('student.notes.update', $note), ['title' => 'ok', 'body' => str_repeat('a', StudentNote::MAX_BODY + 1)])
            ->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_show_update_and_delete_my_note(): void
    {
        $me = User::factory()->create();
        $note = $this->note($me, ['body' => 'hello']);

        $this->actingAs($me)->getJson(route('student.notes.show', $note))->assertOk()->assertJsonPath('body', 'hello');

        $this->actingAs($me)->putJson(route('student.notes.update', $note), ['title' => 'New', 'body' => "# x\n<script>alert(1)</script>"])
            ->assertOk()->assertJsonPath('title', 'New');
        $this->assertSame("# x\n<script>alert(1)</script>", $note->fresh()->body); // يُخزَّن كما هو، ويُهرَّب عند العرض

        $this->actingAs($me)->deleteJson(route('student.notes.destroy', $note))->assertOk();
        $this->assertDatabaseMissing('student_notes', ['id' => $note->id]);
    }

    public function test_another_students_note_is_not_found(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $note = $this->note($other, ['body' => 'private']);

        $this->actingAs($me)->getJson(route('student.notes.show', $note))->assertNotFound();
        $this->actingAs($me)->putJson(route('student.notes.update', $note), ['title' => 'hack', 'body' => 'x'])->assertNotFound();
        $this->actingAs($me)->deleteJson(route('student.notes.destroy', $note))->assertNotFound();

        $this->assertSame('private', $note->fresh()->body);
    }

    public function test_note_limit_is_enforced(): void
    {
        $me = User::factory()->create();
        $rows = [];
        for ($i = 0; $i < StudentNote::MAX_PER_USER; $i++) {
            $rows[] = ['user_id' => $me->id, 'title' => "n$i", 'body' => '', 'created_at' => now(), 'updated_at' => now()];
        }
        StudentNote::insert($rows);

        $this->actingAs($me)->postJson(route('student.notes.store'), ['title' => 'one too many'])->assertStatus(422);
    }

    public function test_deleting_a_course_keeps_the_note_in_the_general_folder(): void
    {
        $me = User::factory()->create();
        $course = $this->enrolled($me);
        $note = $this->note($me, ['course_id' => $course->id]);

        Enrollment::query()->delete();
        $course->forceDelete();

        $this->assertNull($note->fresh()->course_id);
    }

    /* ---------- السبورة ---------- */

    public function test_create_board_pages_with_default_names_and_a_cap(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->postJson(route('student.board.store'))->assertCreated()->assertJsonStructure(['id', 'name']);
        $this->actingAs($me)->postJson(route('student.board.store'), ['name' => 'Algebra'])->assertCreated()->assertJsonPath('name', 'Algebra');

        for ($i = StudentBoardPage::where('user_id', $me->id)->count(); $i < StudentBoardPage::MAX_PER_USER; $i++) {
            $this->page($me);
        }
        $this->actingAs($me)->postJson(route('student.board.store'))->assertStatus(422);
    }

    public function test_saving_a_page_sanitizes_the_objects(): void
    {
        $me = User::factory()->create();
        $page = $this->page($me);
        $png = 'data:image/png;base64,iVBORw0KGgo=';

        $this->actingAs($me)->putJson(route('student.board.update', $page), ['objects' => [
            ['t' => 'p', 'c' => '#FF0000', 'w' => 5, 'p' => [[1, 2], [3.14159, 4], ['x', 1], [1]]],   // نقطتان صالحتان
            ['t' => 'p', 'c' => 'red', 'w' => 5, 'p' => [[1, 2]]],                                  // لون غير صالح
            ['t' => 'p', 'c' => '#00ff00', 'w' => 9999, 'p' => [[1, 2]]],                           // سماكة مرفوضة
            ['t' => 'l', 'c' => '#00ff00', 'w' => 2, 'x1' => 0, 'y1' => 0, 'x2' => 10, 'y2' => 10],
            ['t' => 'r', 'c' => '#00ff00', 'w' => 2, 'x' => 1, 'y' => 1, 'bw' => 5, 'bh' => 5, 'evil' => '<script>'],
            ['t' => 'o', 'c' => '#00ff00', 'w' => 2, 'x' => 1, 'y' => 1, 'bw' => 99999, 'bh' => 5],  // إحداثيات خارج الحد
            ['t' => 'i', 'src' => 'javascript:alert(1)', 'x' => 0, 'y' => 0, 'bw' => 5, 'bh' => 5],
            ['t' => 'i', 'src' => 'data:image/svg+xml;base64,PHN2Zz4=', 'x' => 0, 'y' => 0, 'bw' => 5, 'bh' => 5],
            ['t' => 'i', 'src' => $png, 'x' => 0, 'y' => 0, 'bw' => 5, 'bh' => 5],
            ['t' => 'script', 'c' => '#00ff00'],
            'not-an-object',
        ]])->assertOk();

        $saved = json_decode($page->fresh()->objects, true);
        $this->assertSame(['p', 'l', 'r', 'i'], array_column($saved, 't'));
        $this->assertSame('#ff0000', $saved[0]['c']);
        $this->assertEquals([[1, 2], [3.1, 4]], $saved[0]['p']); // JSON لا يميّز 1 عن 1.0
        $this->assertArrayNotHasKey('evil', $saved[2]);
        $this->assertSame($png, $saved[3]['src']);

        $this->actingAs($me)->getJson(route('student.board.show', $page))->assertOk()->assertJsonCount(4, 'objects');
    }

    public function test_eraser_color_is_forced_and_name_can_be_renamed(): void
    {
        $me = User::factory()->create();
        $page = $this->page($me);

        $this->actingAs($me)->putJson(route('student.board.update', $page), [
            'name' => 'Renamed', 'objects' => [['t' => 'e', 'c' => '#ff00ff', 'w' => 20, 'p' => [[5, 5]]]],
        ])->assertOk()->assertJsonPath('name', 'Renamed');

        $this->assertSame('#000000', json_decode($page->fresh()->objects, true)[0]['c']);
    }

    public function test_another_students_page_is_not_found(): void
    {
        $me = User::factory()->create();
        $page = $this->page(User::factory()->create(), ['objects' => '[{"t":"l"}]']);

        $this->actingAs($me)->getJson(route('student.board.show', $page))->assertNotFound();
        $this->actingAs($me)->putJson(route('student.board.update', $page), ['objects' => []])->assertNotFound();
        $this->actingAs($me)->deleteJson(route('student.board.destroy', $page))->assertNotFound();
        $this->assertDatabaseHas('student_board_pages', ['id' => $page->id]);
    }

    public function test_delete_my_page_and_too_many_objects_are_rejected(): void
    {
        $me = User::factory()->create();
        $page = $this->page($me);

        $tooMany = array_fill(0, BoardSanitizer::MAX_OBJECTS + 1, ['t' => 'l', 'c' => '#ffffff', 'w' => 2, 'x1' => 0, 'y1' => 0, 'x2' => 1, 'y2' => 1]);
        $this->actingAs($me)->putJson(route('student.board.update', $page), ['objects' => $tooMany])->assertStatus(422);

        $this->actingAs($me)->deleteJson(route('student.board.destroy', $page))->assertOk();
        $this->assertDatabaseMissing('student_board_pages', ['id' => $page->id]);
    }

    public function test_sanitizer_handles_non_array_input_and_total_size(): void
    {
        $this->assertSame([], BoardSanitizer::clean('x'));
        $this->assertSame([], BoardSanitizer::clean(null));

        $big = 'data:image/png;base64,' . str_repeat('A', 800000);
        $objs = array_fill(0, 6, ['t' => 'i', 'src' => $big, 'x' => 0, 'y' => 0, 'bw' => 5, 'bh' => 5]);
        $this->assertLessThanOrEqual(3, count(BoardSanitizer::clean($objs))); // الإجمالي محدود بـ3MB تقريبًا

        $tooBig = ['t' => 'i', 'src' => 'data:image/png;base64,' . str_repeat('A', BoardSanitizer::MAX_IMAGE_CHARS), 'x' => 0, 'y' => 0, 'bw' => 5, 'bh' => 5];
        $this->assertSame([], BoardSanitizer::clean([$tooBig]));
    }

    /* ---------- لوحة التحكم (الموظفون) ---------- */

    private function staffUser(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('orders.view', 'web'));

        return $u;
    }

    public function test_cms_notes_page_is_staff_only(): void
    {
        $this->get(route('cms.admin.notes.index'))->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get(route('cms.admin.notes.index'))
            ->assertRedirect(route('student.courses.mine'));

        $this->actingAs($this->staffUser())
            ->get(route('cms.admin.notes.index'))
            ->assertOk();
    }

    public function test_staff_can_file_notes_only_under_their_visible_courses(): void
    {
        $staff = $this->staffUser();
        $own = Course::factory()->published()->create(['instructor_id' => $staff->id]);
        $foreign = Course::factory()->published()->create();

        $this->actingAs($staff)
            ->postJson(route('student.notes.store'), ['title' => 'a', 'body' => 'b', 'course_id' => $own->id])
            ->assertCreated();

        $this->actingAs($staff)
            ->postJson(route('student.notes.store'), ['title' => 'a', 'body' => 'b', 'course_id' => $foreign->id])
            ->assertStatus(422);
    }

    public function test_non_staff_cannot_file_under_a_course_they_do_not_attend(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();

        $this->actingAs($student)
            ->postJson(route('student.notes.store'), ['title' => 'a', 'body' => 'b', 'course_id' => $course->id])
            ->assertStatus(422);
    }
}
