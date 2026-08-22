<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\SectionUserSchoolRole;
use App\Models\Timesheet;
use Illuminate\Database\Seeder;

/**
 * Seeder additif, idempotent : ne touche ni aux users/écoles existants, ne
 * duplique rien (Attendance::firstOrCreate sur la contrainte unique
 * timesheet_id/section_user_id). Peut être relancé sans risque.
 *
 * Reproduit la logique d'éligibilité d'App\Concerns\ResolvesAttendanceRoster :
 * élèves (rôle ELEVE) de la section du timesheet.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $timesheets = Timesheet::with('schedule.sectionCourse.sectionUser')->get();
        $created = 0;

        foreach ($timesheets as $timesheet) {
            $sectionId = $timesheet->schedule?->sectionCourse?->sectionUser?->section_id;

            if (! $sectionId) {
                continue;
            }

            $students = SectionUserSchoolRole::where('section_id', $sectionId)
                ->where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->get();

            foreach ($students as $student) {
                $isPresent = fake()->boolean(85); // 85% de présence, pour avoir des absences à voir

                Attendance::firstOrCreate(
                    ['timesheet_id' => $timesheet->id, 'section_user_id' => $student->id],
                    [
                        'is_present' => $isPresent,
                        'note' => $isPresent ? null : fake()->randomElement(['Malade', 'Justifié', 'Absence non justifiée']),
                        'status' => 'A',
                        'is_active' => true,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );
                $created++;
            }
        }

        $this->command?->info("AttendanceSeeder : {$created} présence(s) traitée(s) (créées ou déjà existantes).");
    }
}
