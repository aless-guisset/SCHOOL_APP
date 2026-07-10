<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\UserSchoolRole;
use Illuminate\Database\Seeder;

class SectionUserSeeder extends Seeder
{
    public function run(): void
    {
        $userSchoolRoleIds = UserSchoolRole::pluck('id')->shuffle();
        $sectionIds = Section::pluck('id');

        if ($userSchoolRoleIds->isEmpty() || $sectionIds->isEmpty()) {
            return;
        }

        $created = 0;
        $target = 20;

        // Génère des paires (user_school_role_id, section_id) uniques
        foreach ($userSchoolRoleIds as $usrId) {
            foreach ($sectionIds->shuffle() as $sectionId) {
                SectionUserSchoolRole::firstOrCreate(
                    [
                        'user_school_role_id' => $usrId,
                        'section_id'          => $sectionId,
                    ],
                    [
                        'status'     => 'A',
                        'is_active'  => true,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );

                if (++$created >= $target) {
                    return;
                }
            }
        }
    }
}
