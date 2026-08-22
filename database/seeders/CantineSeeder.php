<?php

namespace Database\Seeders;

use App\Models\CantinePresence;
use App\Models\CantineRegistration;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder additif, idempotent : active le module cantine sur les écoles qui
 * ont des élèves, inscrit une partie d'entre eux sur 2-3 jours récurrents,
 * puis marque des présences sur les 2 dernières occurrences de chaque jour.
 * Rien n'est dupliqué (contraintes uniques + firstOrCreate/updateOrCreate) et
 * aucun user/école existant n'est modifié à part le flag cantine_enabled.
 */
class CantineSeeder extends Seeder
{
    private const DAYS = [1, 3, 5]; // lundi, mercredi, vendredi

    public function run(): void
    {
        $schools = School::where('is_active', true)->get();
        $registrations = 0;
        $presences = 0;

        foreach ($schools as $school) {
            $students = SectionUserSchoolRole::where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $school->id)
                    ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $school->update(['cantine_enabled' => true]);

            // Inscrit ~60% des élèves de l'école, sur 1 à 2 jours chacun.
            foreach ($students as $student) {
                if (! fake()->boolean(60)) {
                    continue;
                }

                $days = fake()->randomElements(self::DAYS, fake()->numberBetween(1, 2));

                foreach ($days as $day) {
                    $registration = CantineRegistration::firstOrCreate(
                        ['section_user_id' => $student->id, 'day_of_week' => $day],
                        [
                            'school_id' => $school->id,
                            'status' => 'A',
                            'is_active' => true,
                            'created_by' => 1,
                            'updated_by' => 1,
                        ]
                    );
                    $registrations++;

                    // Présences sur les 2 dernières occurrences passées de ce jour.
                    foreach ([1, 2] as $occurrence) {
                        $date = Carbon::now()->subWeeks($occurrence)->startOfWeek(Carbon::MONDAY)->addDays($day - 1);
                        $isPresent = fake()->boolean(90);

                        CantinePresence::firstOrCreate(
                            ['cantine_registration_id' => $registration->id, 'date' => $date->toDateString()],
                            [
                                'is_present' => $isPresent,
                                'note' => $isPresent ? null : 'Absent, non signalé',
                                'status' => 'A',
                                'is_active' => true,
                                'created_by' => 1,
                                'updated_by' => 1,
                            ]
                        );
                        $presences++;
                    }
                }
            }
        }

        $this->command?->info("CantineSeeder : {$registrations} inscription(s), {$presences} présence(s) traitée(s).");
    }
}
