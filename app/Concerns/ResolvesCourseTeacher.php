<?php

namespace App\Concerns;

use App\Models\SectionCourse;
use App\Models\UserSchoolRole;

trait ResolvesCourseTeacher
{
    /**
     * Un SectionCourse est "à ce professeur" soit parce qu'un de ses schedules
     * l'a comme affectation par défaut (schedules.user_school_role_id — le vrai
     * schéma de prod/du seeder de démo, section_user_id de la SectionCourse
     * pointant alors un élève-placeholder, voir
     * DemoSchoolSeeder::makeSectionCourses()), soit parce que $usr est
     * lui-même le section_user ancré à la SectionCourse (ancien schéma où
     * section_user_id pointait directement le professeur). Même réconciliation
     * que SchedulesController::scopeToProfessor() (commit 0463688) — les deux
     * conventions coexistent dans les données existantes.
     */
    protected function professorTeachesSectionCourse(SectionCourse $sectionCourse, UserSchoolRole $usr): bool
    {
        $viaDefaultAssignment = $sectionCourse->schedules()->where('user_school_role_id', $usr->id)->exists();
        $viaLegacySectionUser = $sectionCourse->sectionUser?->user_school_role_id === $usr->id;

        return $viaDefaultAssignment || $viaLegacySectionUser;
    }
}
