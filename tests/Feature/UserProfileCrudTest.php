<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileCrudTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /* ---------- Index ---------- */

    public function test_index_page_renders(): void
    {
        $this->get(route('cms.admin.user-profiles.index'))
            ->assertOk()
            ->assertViewIs('cms.user-profiles.index');
    }

    public function test_index_json_returns_html_and_real_stats(): void
    {
        UserProfile::factory()->create();
        UserProfile::factory()->create(['avatar' => 'avatars/a.jpg']);
        UserProfile::factory()->create()->delete();

        $this->getJson(route('cms.admin.user-profiles.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.active', 2)
            ->assertJsonPath('stats.trashed', 1)
            ->assertJsonPath('stats.with_avatar', 1)
            ->assertJsonStructure(['html', 'stats']);
    }

    public function test_index_search_filters_results(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Zedman']);
        $bob   = User::factory()->create(['name' => 'Bob Quimby']);
        UserProfile::factory()->create(['user_id' => $alice->id]);
        UserProfile::factory()->create(['user_id' => $bob->id]);

        $html = $this->getJson(route('cms.admin.user-profiles.index', ['search' => 'Zedman']))
            ->assertOk()->json('html');

        $this->assertStringContainsString('Alice Zedman', $html);
        $this->assertStringNotContainsString('Bob Quimby', $html);
    }

    public function test_index_filters_by_user_and_ignores_invalid_sort_column(): void
    {
        $profile = UserProfile::factory()->create();
        UserProfile::factory()->create();

        $html = $this->getJson(route('cms.admin.user-profiles.index', [
            'user_id' => $profile->user_id,
            'sort'    => 'password; drop table users',
        ]))->assertOk()->json('html');

        $this->assertStringContainsString($profile->user->email, $html);
    }

    public function test_index_does_not_list_trashed_records(): void
    {
        $profile = UserProfile::factory()->create();
        $profile->delete();

        $html = $this->getJson(route('cms.admin.user-profiles.index'))->json('html');

        $this->assertStringNotContainsString($profile->user->email, $html);
    }

    /* ---------- Create / Store ---------- */

    public function test_create_returns_only_users_without_profiles(): void
    {
        $withProfile = UserProfile::factory()->create()->user;
        $trashedOwner = UserProfile::factory()->create();
        $trashedOwner->delete();
        $free = User::factory()->create();

        $ids = collect($this->getJson(route('cms.admin.user-profiles.create'))->assertOk()->json('users'))->pluck('id');

        $this->assertTrue($ids->contains($free->id));
        $this->assertFalse($ids->contains($withProfile->id));
        $this->assertFalse($ids->contains($trashedOwner->user_id));
    }

    public function test_store_creates_profile_with_avatar(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('cms.admin.user-profiles.store'), [
            'user_id' => $user->id,
            'bio'     => 'Backend developer',
            'phone'   => '+970599000000',
            'website' => 'https://example.com',
            'avatar'  => UploadedFile::fake()->image('me.jpg', 200, 200),
        ], self::JSON);

        $response->assertCreated()->assertJsonPath('data.user_id', $user->id);

        $profile = UserProfile::firstWhere('user_id', $user->id);
        $this->assertNotNull($profile->avatar);
        Storage::disk('public')->assertExists($profile->avatar);
    }

    public function test_store_requires_user_id(): void
    {
        $this->postJson(route('cms.admin.user-profiles.store'), ['bio' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_store_rejects_nonexistent_user(): void
    {
        $this->postJson(route('cms.admin.user-profiles.store'), ['user_id' => 99999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_store_rejects_user_who_already_has_profile(): void
    {
        $profile = UserProfile::factory()->create();

        $this->postJson(route('cms.admin.user-profiles.store'), ['user_id' => $profile->user_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_store_rejects_user_whose_profile_is_in_trash(): void
    {
        $profile = UserProfile::factory()->create();
        $profile->delete();

        $this->postJson(route('cms.admin.user-profiles.store'), ['user_id' => $profile->user_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_store_validates_website_phone_and_avatar(): void
    {
        $user = User::factory()->create();

        $this->post(route('cms.admin.user-profiles.store'), [
            'user_id' => $user->id,
            'website' => 'not-a-url',
            'phone'   => str_repeat('1', 51),
            'avatar'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], self::JSON)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website', 'phone', 'avatar']);
    }

    public function test_store_rejects_oversized_avatar(): void
    {
        $user = User::factory()->create();

        $this->post(route('cms.admin.user-profiles.store'), [
            'user_id' => $user->id,
            'avatar'  => UploadedFile::fake()->image('big.jpg')->size(3000),
        ], self::JSON)->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }

    /* ---------- Show / Edit ---------- */

    public function test_show_returns_profile_with_user(): void
    {
        $profile = UserProfile::factory()->create();

        $this->getJson(route('cms.admin.user-profiles.show', $profile))
            ->assertOk()
            ->assertJsonPath('data.id', $profile->id)
            ->assertJsonPath('data.user.email', $profile->user->email);
    }

    public function test_show_returns_404_for_trashed_record(): void
    {
        $profile = UserProfile::factory()->create();
        $profile->delete();

        $this->getJson(route('cms.admin.user-profiles.show', $profile->id))->assertNotFound();
    }

    public function test_edit_returns_record_and_users(): void
    {
        $profile = UserProfile::factory()->create();

        $this->getJson(route('cms.admin.user-profiles.edit', $profile))
            ->assertOk()
            ->assertJsonPath('data.id', $profile->id)
            ->assertJsonStructure(['data', 'users']);
    }

    /* ---------- Update ---------- */

    public function test_update_changes_fields_and_allows_same_user_id(): void
    {
        $profile = UserProfile::factory()->create();

        $this->putJson(route('cms.admin.user-profiles.update', $profile), [
            'user_id' => $profile->user_id,
            'phone'   => '0599111222',
            'bio'     => 'Updated bio',
        ])->assertOk();

        $this->assertDatabaseHas('user_profiles', [
            'id' => $profile->id,
            'phone' => '0599111222',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_update_rejects_user_id_owned_by_another_profile(): void
    {
        $a = UserProfile::factory()->create();
        $b = UserProfile::factory()->create();

        $this->putJson(route('cms.admin.user-profiles.update', $a), ['user_id' => $b->user_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_update_replaces_avatar_and_deletes_old_file(): void
    {
        $old = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $profile = UserProfile::factory()->create(['avatar' => $old]);

        $this->post(route('cms.admin.user-profiles.update', $profile), [
            '_method' => 'PUT',
            'user_id' => $profile->user_id,
            'avatar'  => UploadedFile::fake()->image('new.jpg'),
        ], self::JSON)->assertOk();

        $profile->refresh();
        $this->assertNotEquals($old, $profile->avatar);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($profile->avatar);
    }

    public function test_update_can_remove_avatar(): void
    {
        $old = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $profile = UserProfile::factory()->create(['avatar' => $old]);

        $this->putJson(route('cms.admin.user-profiles.update', $profile), [
            'user_id'       => $profile->user_id,
            'remove_avatar' => true,
        ])->assertOk();

        $this->assertNull($profile->fresh()->avatar);
        Storage::disk('public')->assertMissing($old);
    }

    /* ---------- Soft delete / Trash ---------- */

    public function test_destroy_soft_deletes_and_keeps_avatar_file(): void
    {
        $path = UploadedFile::fake()->image('a.jpg')->store('avatars', 'public');
        $profile = UserProfile::factory()->create(['avatar' => $path]);

        $this->deleteJson(route('cms.admin.user-profiles.destroy', $profile))
            ->assertOk()
            ->assertJsonPath('stats.trashed', 1);

        $this->assertSoftDeleted('user_profiles', ['id' => $profile->id]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_trashed_lists_only_deleted_records(): void
    {
        $deleted = UserProfile::factory()->create();
        $deleted->delete();
        $active = UserProfile::factory()->create();

        $html = $this->getJson(route('cms.admin.user-profiles.trashed'))->assertOk()->json('html');

        $this->assertStringContainsString($deleted->user->email, $html);
        $this->assertStringNotContainsString($active->user->email, $html);
    }

    public function test_restore_brings_record_back(): void
    {
        $profile = UserProfile::factory()->create();
        $profile->delete();

        $this->patchJson(route('cms.admin.user-profiles.restore', $profile->id))
            ->assertOk()
            ->assertJsonPath('stats.trashed', 0);

        $this->assertNotSoftDeleted('user_profiles', ['id' => $profile->id]);
    }

    public function test_restore_returns_404_for_record_not_in_trash(): void
    {
        $profile = UserProfile::factory()->create();

        $this->patchJson(route('cms.admin.user-profiles.restore', $profile->id))->assertNotFound();
    }

    public function test_force_delete_removes_record_and_avatar_file(): void
    {
        $path = UploadedFile::fake()->image('a.jpg')->store('avatars', 'public');
        $profile = UserProfile::factory()->create(['avatar' => $path]);
        $profile->delete();

        $this->deleteJson(route('cms.admin.user-profiles.force-delete', $profile->id))->assertOk();

        $this->assertDatabaseMissing('user_profiles', ['id' => $profile->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_force_delete_returns_404_for_active_record(): void
    {
        $profile = UserProfile::factory()->create();

        $this->deleteJson(route('cms.admin.user-profiles.force-delete', $profile->id))->assertNotFound();
        $this->assertDatabaseHas('user_profiles', ['id' => $profile->id]);
    }

    /* ---------- Relationships ---------- */

    public function test_profile_belongs_to_user_and_user_has_one_profile(): void
    {
        $profile = UserProfile::factory()->create();

        $this->assertTrue($profile->user->is($profile->user));
        $this->assertTrue($profile->user->profile->is($profile));
    }

    public function test_deleting_user_cascades_to_profile(): void
    {
        $profile = UserProfile::factory()->create();
        $profile->user->delete();

        $this->assertDatabaseMissing('user_profiles', ['id' => $profile->id]);
    }

    /* ---------- Authorization ---------- */

    public function test_guest_cannot_access_routes(): void
    {
        // يصلح فقط إذا كانت المسارات داخل مجموعة middleware المصادقة في مشروعك.
        auth()->logout();

        $this->getJson(route('cms.admin.user-profiles.index'))->assertUnauthorized();
    }
}
