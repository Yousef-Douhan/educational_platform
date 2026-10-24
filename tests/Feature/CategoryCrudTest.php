<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [];
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $names[] = Permission::findOrCreate("categories.{$action}", 'web')->name;
        }

        $admin = User::factory()->create();
        $admin->givePermissionTo($names);
        $this->actingAs($admin);
    }

    /* ---------- Index ---------- */

    public function test_index_page_renders(): void
    {
        $this->get(route('cms.admin.categories.index'))->assertOk()->assertViewIs('cms.categories.index');
    }

    public function test_index_json_returns_real_stats(): void
    {
        $root  = Category::factory()->create();
        Category::factory()->create(['parent_id' => $root->id]);
        Category::factory()->create()->delete();

        $this->getJson(route('cms.admin.categories.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.roots', 1)
            ->assertJsonPath('stats.subs', 1)
            ->assertJsonPath('stats.with_children', 1)
            ->assertJsonPath('stats.trashed', 1);
    }

    public function test_index_search_matches_names_and_slug(): void
    {
        Category::factory()->create(['name_en' => 'Zebra Studies', 'slug' => 'zebra-studies']);
        Category::factory()->create(['name_en' => 'Quokka Care', 'slug' => 'quokka-care']);

        $html = $this->getJson(route('cms.admin.categories.index', ['search' => 'zebra']))->json('html');

        $this->assertStringContainsString('Zebra Studies', $html);
        $this->assertStringNotContainsString('Quokka Care', $html);
    }

    public function test_index_filters_by_type_and_parent(): void
    {
        $root = Category::factory()->create(['name_en' => 'RootOne']);
        Category::factory()->create(['name_en' => 'ChildOne', 'parent_id' => $root->id]);
        Category::factory()->create(['name_en' => 'RootTwo']);

        $roots = $this->getJson(route('cms.admin.categories.index', ['type' => 'root']))->json('html');
        $this->assertStringContainsString('RootTwo', $roots);
        $this->assertStringNotContainsString('ChildOne', $roots);

        $subs = $this->getJson(route('cms.admin.categories.index', ['type' => 'sub']))->json('html');
        $this->assertStringContainsString('ChildOne', $subs);
        $this->assertStringNotContainsString('RootTwo', $subs);

        $byParent = $this->getJson(route('cms.admin.categories.index', ['parent_id' => $root->id]))->json('html');
        $this->assertStringContainsString('ChildOne', $byParent);
        $this->assertStringNotContainsString('RootTwo', $byParent);
    }

    public function test_index_ignores_invalid_sort_column(): void
    {
        Category::factory()->create();

        $this->getJson(route('cms.admin.categories.index', ['sort' => 'slug; drop table categories']))->assertOk();
    }

    public function test_index_does_not_list_trashed_records(): void
    {
        $cat = Category::factory()->create(['name_en' => 'Hidden Gem']);
        $cat->delete();

        $html = $this->getJson(route('cms.admin.categories.index'))->json('html');

        $this->assertStringNotContainsString('Hidden Gem', $html);
    }

    /* ---------- Create / Store ---------- */

    public function test_create_returns_parent_options(): void
    {
        $cat = Category::factory()->create();

        $ids = collect($this->getJson(route('cms.admin.categories.create'))->assertOk()->json('parents'))->pluck('id');

        $this->assertTrue($ids->contains($cat->id));
    }

    public function test_store_creates_category_with_given_slug(): void
    {
        $this->postJson(route('cms.admin.categories.store'), [
            'name_ar' => 'البرمجة',
            'name_en' => 'Programming',
            'slug' => 'programming',
            'icon' => 'fa-solid fa-code',
        ])->assertCreated();

        $this->assertDatabaseHas('categories', ['slug' => 'programming', 'icon' => 'fa-solid fa-code']);
    }

    public function test_store_generates_slug_when_empty_and_keeps_it_unique(): void
    {
        $payload = ['name_ar' => 'تصميم', 'name_en' => 'Graphic Design'];

        $this->postJson(route('cms.admin.categories.store'), $payload)->assertCreated();
        $this->postJson(route('cms.admin.categories.store'), $payload)->assertCreated();

        $this->assertDatabaseHas('categories', ['slug' => 'graphic-design']);
        $this->assertDatabaseHas('categories', ['slug' => 'graphic-design-2']);
    }

    public function test_store_can_create_sub_category(): void
    {
        $parent = Category::factory()->create();

        $this->postJson(route('cms.admin.categories.store'), [
            'name_ar' => 'فرعية',
            'name_en' => 'Sub',
            'parent_id' => $parent->id,
        ])->assertCreated()->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_store_requires_both_names(): void
    {
        $this->postJson(route('cms.admin.categories.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_ar', 'name_en']);
    }

    public function test_store_rejects_duplicate_slug_even_if_trashed(): void
    {
        Category::factory()->create(['slug' => 'taken'])->delete();

        $this->postJson(route('cms.admin.categories.store'), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'slug' => 'taken',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_store_rejects_invalid_slug_icon_and_parent(): void
    {
        $this->postJson(route('cms.admin.categories.store'), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'slug' => 'Bad Slug!',
            'icon' => 'fa" onclick="x',
            'parent_id' => 9999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['slug', 'icon', 'parent_id']);
    }

    public function test_store_rejects_trashed_parent(): void
    {
        $parent = Category::factory()->create();
        $parent->delete();

        $this->postJson(route('cms.admin.categories.store'), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'parent_id' => $parent->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    /* ---------- Show / Edit ---------- */

    public function test_show_returns_parent_and_children(): void
    {
        $parent = Category::factory()->create();
        $child  = Category::factory()->create(['parent_id' => $parent->id]);

        $this->getJson(route('cms.admin.categories.show', $parent))
            ->assertOk()
            ->assertJsonPath('data.children_count', 1)
            ->assertJsonPath('children.0.id', $child->id);

        $this->getJson(route('cms.admin.categories.show', $child))
            ->assertOk()
            ->assertJsonPath('data.parent.id', $parent->id);
    }

    public function test_show_returns_404_for_trashed_record(): void
    {
        $cat = Category::factory()->create();
        $cat->delete();

        $this->getJson(route('cms.admin.categories.show', $cat->id))->assertNotFound();
    }

    public function test_edit_excludes_self_and_descendants_from_parent_options(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);
        $c = Category::factory()->create(['parent_id' => $b->id]);
        $other = Category::factory()->create();

        $ids = collect($this->getJson(route('cms.admin.categories.edit', $a))->assertOk()->json('parents'))->pluck('id');

        $this->assertFalse($ids->contains($a->id));
        $this->assertFalse($ids->contains($b->id));
        $this->assertFalse($ids->contains($c->id));
        $this->assertTrue($ids->contains($other->id));
    }

    /* ---------- Update ---------- */

    public function test_update_changes_fields_and_keeps_slug_when_blank(): void
    {
        $cat = Category::factory()->create(['slug' => 'original']);

        $this->putJson(route('cms.admin.categories.update', $cat), [
            'name_ar' => 'جديد',
            'name_en' => 'New',
            'slug' => '',
        ])->assertOk();

        $this->assertDatabaseHas('categories', ['id' => $cat->id, 'name_en' => 'New', 'slug' => 'original']);
    }

    public function test_update_allows_same_slug_but_rejects_anothers(): void
    {
        $a = Category::factory()->create(['slug' => 'a']);
        Category::factory()->create(['slug' => 'b']);

        $this->putJson(route('cms.admin.categories.update', $a), ['name_ar' => 'أ', 'name_en' => 'A', 'slug' => 'a'])->assertOk();
        $this->putJson(route('cms.admin.categories.update', $a), ['name_ar' => 'أ', 'name_en' => 'A', 'slug' => 'b'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_update_rejects_self_as_parent(): void
    {
        $cat = Category::factory()->create();

        $this->putJson(route('cms.admin.categories.update', $cat), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'parent_id' => $cat->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_update_rejects_descendant_as_parent(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);
        $c = Category::factory()->create(['parent_id' => $b->id]);

        $this->putJson(route('cms.admin.categories.update', $a), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'parent_id' => $c->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_update_can_clear_parent_and_icon(): void
    {
        $parent = Category::factory()->create();
        $cat    = Category::factory()->create(['parent_id' => $parent->id, 'icon' => 'fa-solid fa-book']);

        $this->putJson(route('cms.admin.categories.update', $cat), [
            'name_ar' => 'أ',
            'name_en' => 'A',
            'parent_id' => null,
            'icon' => null,
        ])->assertOk();

        $cat->refresh();
        $this->assertNull($cat->parent_id);
        $this->assertNull($cat->icon);
    }

    /* ---------- Soft delete / Trash ---------- */

    public function test_destroy_soft_deletes(): void
    {
        $cat = Category::factory()->create();

        $this->deleteJson(route('cms.admin.categories.destroy', $cat))
            ->assertOk()->assertJsonPath('stats.trashed', 1);

        $this->assertSoftDeleted('categories', ['id' => $cat->id]);
    }

    public function test_destroy_is_blocked_when_category_has_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->deleteJson(route('cms.admin.categories.destroy', $parent))->assertStatus(409);

        $this->assertNotSoftDeleted('categories', ['id' => $parent->id]);
    }

    public function test_trashed_lists_only_deleted_records(): void
    {
        $gone = Category::factory()->create(['name_en' => 'GoneCat']);
        $gone->delete();
        Category::factory()->create(['name_en' => 'StayCat']);

        $html = $this->getJson(route('cms.admin.categories.trashed'))->assertOk()->json('html');

        $this->assertStringContainsString('GoneCat', $html);
        $this->assertStringNotContainsString('StayCat', $html);
    }

    public function test_restore_brings_record_back(): void
    {
        $cat = Category::factory()->create();
        $cat->delete();

        $this->patchJson(route('cms.admin.categories.restore', $cat->id))
            ->assertOk()->assertJsonPath('stats.trashed', 0);

        $this->assertNotSoftDeleted('categories', ['id' => $cat->id]);
    }

    public function test_restore_is_blocked_while_parent_is_trashed(): void
    {
        $parent = Category::factory()->create();
        $child  = Category::factory()->create(['parent_id' => $parent->id]);
        $child->delete();
        $parent->delete();

        $this->patchJson(route('cms.admin.categories.restore', $child->id))->assertStatus(409);

        $this->assertSoftDeleted('categories', ['id' => $child->id]);
    }

    public function test_restore_returns_404_for_record_not_in_trash(): void
    {
        $cat = Category::factory()->create();

        $this->patchJson(route('cms.admin.categories.restore', $cat->id))->assertNotFound();
    }

    public function test_force_delete_removes_record(): void
    {
        $cat = Category::factory()->create();
        $cat->delete();

        $this->deleteJson(route('cms.admin.categories.force-delete', $cat->id))->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    public function test_force_delete_is_blocked_when_trashed_children_exist(): void
    {
        $parent = Category::factory()->create();
        $child  = Category::factory()->create(['parent_id' => $parent->id]);
        $child->delete();
        $parent->delete();

        $this->deleteJson(route('cms.admin.categories.force-delete', $parent->id))->assertStatus(409);

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_force_delete_returns_404_for_active_record(): void
    {
        $cat = Category::factory()->create();

        $this->deleteJson(route('cms.admin.categories.force-delete', $cat->id))->assertNotFound();
    }

    /* ---------- Relationships ---------- */

    public function test_parent_and_children_relationships(): void
    {
        $parent = Category::factory()->create();
        $child  = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_name_accessor_follows_locale(): void
    {
        $cat = Category::factory()->create(['name_ar' => 'عربي', 'name_en' => 'English']);

        app()->setLocale('ar');
        $this->assertSame('عربي', $cat->name);

        app()->setLocale('en');
        $this->assertSame('English', $cat->name);
    }

    /* ---------- Authorization ---------- */

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('cms.admin.categories.index'))->assertForbidden();
        $this->postJson(route('cms.admin.categories.store'), ['name_ar' => 'أ', 'name_en' => 'A'])->assertForbidden();
    }

    public function test_user_with_only_view_cannot_delete(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('categories.view');
        $this->actingAs($viewer);

        $cat = Category::factory()->create();

        $this->getJson(route('cms.admin.categories.index'))->assertOk();
        $this->deleteJson(route('cms.admin.categories.destroy', $cat))->assertForbidden();
    }
}
