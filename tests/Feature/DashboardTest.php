<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('a user with exactly one active school is redirected once to establish school context, then can visit the dashboard', function () {
    // `dashboard` est derrière le middleware `school.context`. Pour un
    // utilisateur qui a exactement une école active mais rien en session
    // (ex: première requête après connexion), CheckSchoolContext écrit
    // active_school_id en session PUIS redirige vers la même URL plutôt que
    // de continuer dans la même requête — HandleInertiaRequests, middleware
    // global qui s'exécute plus tôt dans le pipeline que les middlewares de
    // route, partagerait sinon school/currentRole à null pour cette réponse
    // précise (la valeur venant tout juste d'être écrite en session).
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ELEVE'], [
        'name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('dashboard'));

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('currentRole', 'Élève')
        ->where('school.id', $school->id)
    );
});
