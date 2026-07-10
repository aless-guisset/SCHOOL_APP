<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Database\Seeder;

class UserSchoolRoleSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@school.com')->first();
        $profUser  = User::where('email', 'prof@school.com')->first();
        $eleveUser = User::where('email', 'eleve@school.com')->first();

        $schools = School::all();
        $firstSchool = $schools->first();

        $roleAdmin = Role::where('reference', 'ADMIN')->first();
        $roleProf  = Role::where('reference', 'PROF')->first();
        $roleEleve = Role::where('reference', 'ELEVE')->first();

        // ── Comptes de test : assignation garantie ────────────────────────
        // Admin → accès à toutes les écoles
        if ($adminUser && $roleAdmin) {
            foreach ($schools as $school) {
                UserSchoolRole::firstOrCreate(
                    ['user_id' => $adminUser->id, 'school_id' => $school->id, 'role_id' => $roleAdmin->id],
                    ['is_active' => true, 'status' => 'A', 'created_by' => 1, 'updated_by' => 1]
                );
            }

            // École par défaut = première école
            $adminUser->update(['default_school_id' => $firstSchool?->id]);
        }

        // Prof → première école
        if ($profUser && $firstSchool && $roleProf) {
            UserSchoolRole::firstOrCreate(
                ['user_id' => $profUser->id, 'school_id' => $firstSchool->id, 'role_id' => $roleProf->id],
                ['is_active' => true, 'status' => 'A', 'created_by' => 1, 'updated_by' => 1]
            );
        }

        // Élève → première école
        if ($eleveUser && $firstSchool && $roleEleve) {
            UserSchoolRole::firstOrCreate(
                ['user_id' => $eleveUser->id, 'school_id' => $firstSchool->id, 'role_id' => $roleEleve->id],
                ['is_active' => true, 'status' => 'A', 'created_by' => 1, 'updated_by' => 1]
            );
        }

        // ── Assignations aléatoires pour les autres utilisateurs ──────────
        UserSchoolRole::factory(17)->create();
    }
}
