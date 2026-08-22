<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeder additif, idempotent : une note par élève/matière/période est unique
 * (contrainte section_user_id/subject_id/period), Grade::updateOrCreate évite
 * tout doublon. Aucun user/école/matière existant n'est modifié.
 */
class GradeSeeder extends Seeder
{
    private const PERIODS = ['Trimestre 1', 'Trimestre 2'];

    public function run(): void
    {
        $schools = School::where('is_active', true)->get();
        $created = 0;

        foreach ($schools as $school) {
            $subjects = Subject::whereHas('course', fn ($q) => $q->where('school_id', $school->id))
                ->where('is_active', true)
                ->get();

            if ($subjects->isEmpty()) {
                continue;
            }

            $students = SectionUserSchoolRole::where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $school->id)
                    ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->get();

            foreach ($students as $student) {
                // 2 à 4 matières notées par élève, sur les périodes disponibles.
                $studentSubjects = $subjects->random(min($subjects->count(), fake()->numberBetween(2, 4)));

                foreach ($studentSubjects as $subject) {
                    foreach (self::PERIODS as $period) {
                        if (! fake()->boolean(75)) {
                            continue; // toutes les notes ne sont pas encore rentrées, plus réaliste
                        }

                        Grade::updateOrCreate(
                            ['section_user_id' => $student->id, 'subject_id' => $subject->id, 'period' => $period],
                            [
                                'grade' => fake()->randomFloat(2, 6, 20),
                                'status' => 'A',
                                'is_active' => true,
                                'created_by' => 1,
                                'updated_by' => 1,
                            ]
                        );
                        $created++;
                    }
                }
            }
        }

        $this->command?->info("GradeSeeder : {$created} note(s) traitée(s).");
    }
}
