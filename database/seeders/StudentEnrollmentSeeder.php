<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Database\Seeder;

/**
 * Seeder additif, idempotent — préalable à AttendanceSeeder/CantineSeeder/
 * GradeSeeder. `SectionUserSeeder` (base) associe des `UserSchoolRole` à des
 * sections sans tenir compte du rôle : par construction il ne garantit aucun
 * élève réellement inscrit dans une section, donc les fonctionnalités
 * "élève" (présence, cantine, notes) n'ont rien à afficher.
 *
 * Ici : garantit au moins 4 utilisateurs avec le rôle ELEVE par école
 * (`UserSchoolRole::firstOrCreate`, crée un nouvel utilisateur seulement si
 * nécessaire), puis les inscrit chacun dans une section de leur école
 * (`SectionUserSchoolRole::firstOrCreate`, ne duplique jamais une inscription
 * existante). N'affecte aucune donnée déjà présente.
 */
class StudentEnrollmentSeeder extends Seeder
{
    private const MIN_STUDENTS_PER_SCHOOL = 4;

    public function run(): void
    {
        $eleveRole = Role::where('reference', 'ELEVE')->first();

        if (! $eleveRole) {
            $this->command?->warn('StudentEnrollmentSeeder : rôle ELEVE introuvable, rien à faire.');

            return;
        }

        $schools = School::where('is_active', true)->get();
        $enrolled = 0;
        $usersCreated = 0;

        foreach ($schools as $school) {
            $sections = Section::where('school_id', $school->id)->where('is_active', true)->get();

            if ($sections->isEmpty()) {
                continue;
            }

            $eleveUsrs = UserSchoolRole::where('school_id', $school->id)
                ->where('role_id', $eleveRole->id)
                ->where('is_active', true)
                ->get();

            $missing = self::MIN_STUDENTS_PER_SCHOOL - $eleveUsrs->count();

            for ($i = 0; $i < $missing; $i++) {
                $user = User::factory()->create();
                $usersCreated++;

                $usr = UserSchoolRole::create([
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                    'role_id' => $eleveRole->id,
                    'status' => 'A',
                    'is_active' => true,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
                $eleveUsrs->push($usr);
            }

            foreach ($eleveUsrs as $usr) {
                $section = $sections->random();

                SectionUserSchoolRole::firstOrCreate(
                    ['section_id' => $section->id, 'user_school_role_id' => $usr->id],
                    ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
                );
                $enrolled++;
            }
        }

        $this->command?->info("StudentEnrollmentSeeder : {$enrolled} inscription(s) garantie(s), {$usersCreated} nouvel(le)s élève(s) créé(s).");
    }
}
