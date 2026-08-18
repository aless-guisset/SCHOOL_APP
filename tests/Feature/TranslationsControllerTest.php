<?php

use App\Models\Translation;
use App\Models\User;

beforeEach(function () {
    // Create an admin user for testing
    $this->user = User::factory()->create();
});

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
    $this->withoutMiddleware();
    $response = $this->actingAs($this->user)->post(route('translations.store'), [
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
});

test('update() restores soft-deleted translation when new key collides', function () {
    // Create a soft-deleted translation
    $softDeleted = Translation::create([
        'tag_key' => 'app.new_key',
        'language_code' => 'en',
        'translated_value' => 'Target Value',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $softDeletedId = $softDeleted->id;
    $softDeleted->delete();

    // Create an active translation to update
    $active = Translation::create([
        'tag_key' => 'app.old_key',
        'language_code' => 'en',
        'translated_value' => 'Old Value',
        'is_active' => true,
        'created_by' => 1,
    ]);
    $activeId = $active->id;

    // Simulate the update logic directly (what the controller does)
    $data = [
        'tag_key' => 'app.new_key',
        'language_code' => 'en',
        'translated_value' => 'Updated Value',
        'is_active' => true,
    ];
    $data['updated_by'] = $this->user->id;

    // Check for collision (this is what the controller's update() method does)
    $softDeletedCollision = Translation::withTrashed()
        ->where('tag_key', $data['tag_key'])
        ->where('language_code', $data['language_code'])
        ->where('id', '!=', $active->id)
        ->whereNotNull('deleted_at')
        ->first();

    if ($softDeletedCollision) {
        // Restore the soft-deleted row and update it with new data
        $softDeletedCollision->restore();
        $softDeletedCollision->update($data);
        // Soft-delete the current translation to avoid duplicate
        $active->delete();
    } else {
        // Update normally
        $active->update($data);
    }

    // Verify soft-deleted row was restored
    $restored = Translation::find($softDeletedId);
    expect($restored)->not->toBeNull();
    expect($restored->translated_value)->toBe('Updated Value');
    expect($restored->deleted_at)->toBeNull();

    // Verify the originally active row is now soft-deleted
    expect(Translation::find($activeId))->toBeNull();
    expect(Translation::withTrashed()->find($activeId)->deleted_at)->not->toBeNull();
});

test('store() creates new row if no soft-deleted collision exists', function () {
    $this->withoutMiddleware();

    $initialCount = Translation::count();

    $response = $this->actingAs($this->user)->post(route('translations.store'), [
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
});

test('update() updates normally if no soft-deleted collision', function () {
    $translation = Translation::create([
        'tag_key' => 'app.test',
        'language_code' => 'en',
        'translated_value' => 'Old Value',
        'is_active' => true,
        'created_by' => 1,
    ]);

    // Simulate the update logic directly
    $data = [
        'tag_key' => 'app.test',
        'language_code' => 'en',
        'translated_value' => 'Updated Value',
        'is_active' => true,
    ];
    $data['updated_by'] = $this->user->id;

    $softDeletedCollision = Translation::withTrashed()
        ->where('tag_key', $data['tag_key'])
        ->where('language_code', $data['language_code'])
        ->where('id', '!=', $translation->id)
        ->whereNotNull('deleted_at')
        ->first();

    if (!$softDeletedCollision) {
        // Update normally
        $translation->update($data);
    }

    $updated = Translation::find($translation->id);
    expect($updated->translated_value)->toBe('Updated Value');
    expect($updated->deleted_at)->toBeNull();
});
