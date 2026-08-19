<?php

use App\Models\Role;
use App\Models\School;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // École + rôle Administrateur + utilisateur admin, contexte réel via les middlewares
    $this->school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->adminRole = Role::create([
        'name' => 'Administrateur', 'reference' => 'ADMIN', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->admin = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $this->admin->id, 'school_id' => $this->school->id, 'role_id' => $this->adminRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
});

function actingAsAdmin($test)
{
    return $test->actingAs($test->admin)->withSession(['active_school_id' => $test->school->id]);
}

test('store() restores soft-deleted translation instead of throwing duplicate key error', function () {
    // Create and soft-delete a translation
    $deleted = Translation::create([
        'tag_key' => 'app.title',
        'language_code' => 'en',
        'translated_value' => 'Old Title',
        'screen_name' => 'Home',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $deletedId = $deleted->id;
    $deleted->delete(); // Soft delete

    // Verify it's soft-deleted
    expect(Translation::find($deletedId))->toBeNull();
    expect(Translation::withTrashed()->find($deletedId)->deleted_at)->not->toBeNull();

    // Attempt to recreate the same translation via POST
    $response = actingAsAdmin($this)->post(route('translations.store'), [
        'tag_key' => 'app.title',
        'language_code' => 'en',
        'translated_value' => 'New Title',
        'screen_name' => 'Home',
    ]);

    // Should not throw 500 (duplicate key error), should redirect
    $response->assertStatus(302);

    // Verify the soft-deleted row was restored (same ID, updated value, no deleted_at)
    $restored = Translation::find($deletedId);
    expect($restored)->not->toBeNull();
    expect($restored->translated_value)->toBe('New Title');
    expect($restored->deleted_at)->toBeNull();

    // The original creator attribution must be preserved on restore, not overwritten
    // by the admin performing the restore.
    expect($restored->created_by)->toBe(1);
    expect($restored->updated_by)->toBe($this->admin->id);
});

test('update() hard-deletes a colliding soft-deleted row and preserves the edited row\'s identity', function () {
    // A soft-deleted row already occupies the tag_key + language_code we're about to move into.
    $softDeleted = Translation::create([
        'tag_key' => 'app.new_key',
        'language_code' => 'en',
        'translated_value' => 'Target Value',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $softDeletedId = $softDeleted->id;
    $softDeleted->delete();

    // The translation actually being edited.
    $active = Translation::create([
        'tag_key' => 'app.old_key',
        'language_code' => 'en',
        'translated_value' => 'Old Value',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $activeId = $active->id;

    $response = actingAsAdmin($this)->put(route('translations.update', $active), [
        'tag_key' => 'app.new_key',
        'language_code' => 'en',
        'translated_value' => 'Updated Value',
    ]);

    $response->assertStatus(302);

    // The edited row keeps its own id and now carries the new data — no id-swap.
    $updated = Translation::find($activeId);
    expect($updated)->not->toBeNull();
    expect($updated->id)->toBe($activeId);
    expect($updated->tag_key)->toBe('app.new_key');
    expect($updated->translated_value)->toBe('Updated Value');
    expect($updated->deleted_at)->toBeNull();

    // The colliding soft-deleted row was hard-deleted, not restored.
    expect(Translation::withTrashed()->find($softDeletedId))->toBeNull();
});

test('store() creates new row if no soft-deleted collision exists', function () {
    $initialCount = Translation::count();

    $response = actingAsAdmin($this)->post(route('translations.store'), [
        'tag_key' => 'app.unique_key',
        'language_code' => 'en',
        'translated_value' => 'Unique Value',
        'screen_name' => 'Home',
    ]);

    $response->assertStatus(302);
    expect(Translation::count())->toBe($initialCount + 1);

    $created = Translation::where('tag_key', 'app.unique_key')->first();
    expect($created)->not->toBeNull();
    expect($created->translated_value)->toBe('Unique Value');
    expect($created->created_by)->toBe($this->admin->id);
});

test('update() updates normally if no soft-deleted collision', function () {
    $translation = Translation::create([
        'tag_key' => 'app.test',
        'language_code' => 'en',
        'translated_value' => 'Old Value',
        'is_active' => true,
        'created_by' => 1,
    ]);

    $response = actingAsAdmin($this)->put(route('translations.update', $translation), [
        'tag_key' => 'app.test',
        'language_code' => 'en',
        'translated_value' => 'Updated Value',
    ]);

    $response->assertStatus(302);

    $updated = Translation::find($translation->id);
    expect($updated->id)->toBe($translation->id);
    expect($updated->translated_value)->toBe('Updated Value');
    expect($updated->deleted_at)->toBeNull();
});

test('destroy() soft-deletes the translation, flips is_active, and invalidates the cache', function () {
    $translation = Translation::create([
        'tag_key' => 'app.destroy_me',
        'language_code' => 'en',
        'translated_value' => 'Bye',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Warm the cache for this locale.
    TranslationService::getForLocale('en');
    expect(Cache::has('translations.en'))->toBeTrue();

    $response = actingAsAdmin($this)->delete(route('translations.destroy', $translation));
    $response->assertStatus(302);

    $trashed = Translation::withTrashed()->find($translation->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
    expect($trashed->is_active)->toBeFalse();

    expect(Cache::has('translations.en'))->toBeFalse();
});

test('cache is invalidated after store()', function () {
    // Warm the cache for 'en' before creating a new active translation for it.
    TranslationService::getForLocale('en');
    expect(Cache::has('translations.en'))->toBeTrue();

    actingAsAdmin($this)->post(route('translations.store'), [
        'tag_key' => 'app.cache_store',
        'language_code' => 'en',
        'translated_value' => 'Fresh Value',
    ]);

    expect(Cache::has('translations.en'))->toBeFalse();
    expect(TranslationService::getForLocale('en'))->toHaveKey('app.cache_store');
});

test('cache is invalidated after update() for both old and new locale', function () {
    $translation = Translation::create([
        'tag_key' => 'app.cache_update',
        'language_code' => 'en',
        'translated_value' => 'Before',
        'is_active' => true,
        'created_by' => 1,
    ]);

    TranslationService::getForLocale('en');
    TranslationService::getForLocale('fr');
    expect(Cache::has('translations.en'))->toBeTrue();
    expect(Cache::has('translations.fr'))->toBeTrue();

    actingAsAdmin($this)->put(route('translations.update', $translation), [
        'tag_key' => 'app.cache_update',
        'language_code' => 'fr',
        'translated_value' => 'After',
    ]);

    expect(Cache::has('translations.en'))->toBeFalse();
    expect(Cache::has('translations.fr'))->toBeFalse();
    expect(TranslationService::getForLocale('fr'))->toHaveKey('app.cache_update');
});

test('a non-admin user is forbidden from accessing translations routes', function () {
    $profRole = Role::create([
        'name' => 'Professeur', 'reference' => 'PROF', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $this->school->id, 'role_id' => $profRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['active_school_id' => $this->school->id])
        ->get(route('translations.index'));

    $response->assertStatus(403);
});
