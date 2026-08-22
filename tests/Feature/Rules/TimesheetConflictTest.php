<?php

namespace Tests\Feature\Rules;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Rules\NoTimesheetConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests unitaires pour la règle NoTimesheetConflict.
 *
 * Trois scénarios × (conflit / pas de conflit) = 6 tests.
 * Pattern AAA : Arrange → Act → Assert.
 */
class TimesheetConflictTest extends TestCase
{
    use RefreshDatabase;

    // ── Données partagées ────────────────────────────────────────────────────

    private School             $school;
    private UserSchoolRole     $teacher1;
    private UserSchoolRole     $teacher2;
    private Classroom          $classroom1;
    private Classroom          $classroom2;
    private Subject            $subject;
    private SectionCourse      $sectionCourse1; // → section1
    private SectionCourse      $sectionCourse2; // → section2
    private Schedule           $scheduleA;      // sectionCourse1, 10h–12h lundi
    private Schedule           $scheduleB;      // sectionCourse1, 10h–12h lundi (même section, même créneau)
    private Schedule           $scheduleC;      // sectionCourse2, 10h–12h lundi (section différente)
    private Schedule           $scheduleD;      // sectionCourse1, 08h–10h lundi (créneau non-chevauchant)

    private const DATE = '2026-09-07'; // un lundi

    protected function setUp(): void
    {
        parent::setUp();

        // ── École & rôle ─────────────────────────────────────────────────────
        $this->school = School::create([
            'name' => 'Test School', 'status' => 'A',
            'is_active' => true, 'created_by' => 1,
        ]);

        $role = Role::create([
            'name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
        ]);

        // ── Deux professeurs ─────────────────────────────────────────────────
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->teacher1 = UserSchoolRole::create([
            'user_id' => $user1->id, 'school_id' => $this->school->id,
            'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
        ]);

        $this->teacher2 = UserSchoolRole::create([
            'user_id' => $user2->id, 'school_id' => $this->school->id,
            'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
        ]);

        // ── Deux salles ──────────────────────────────────────────────────────
        $this->classroom1 = Classroom::create([
            'school_id' => $this->school->id, 'name' => 'Salle A',
            'is_active' => true, 'created_by' => 1,
        ]);

        $this->classroom2 = Classroom::create([
            'school_id' => $this->school->id, 'name' => 'Salle B',
            'is_active' => true, 'created_by' => 1,
        ]);

        // ── Cours & matière ──────────────────────────────────────────────────
        $course = Course::create([
            'school_id' => $this->school->id, 'name' => 'Maths',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        $this->subject = Subject::create([
            'course_id' => $course->id, 'name' => 'Algèbre',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // ── Deux sections ────────────────────────────────────────────────────
        $section1 = Section::create([
            'school_id' => $this->school->id, 'name' => 'Classe A',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        $section2 = Section::create([
            'school_id' => $this->school->id, 'name' => 'Classe B',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // SectionUserSchoolRole (pivot section ↔ user_school_role)
        $sectionUser1 = SectionUserSchoolRole::create([
            'section_id'          => $section1->id,
            'user_school_role_id' => $this->teacher1->id,
            'status' => 'A', 'is_active' => true, 'created_by' => 1,
        ]);

        $sectionUser2 = SectionUserSchoolRole::create([
            'section_id'          => $section2->id,
            'user_school_role_id' => $this->teacher2->id,
            'status' => 'A', 'is_active' => true, 'created_by' => 1,
        ]);

        // ── SectionCourses ───────────────────────────────────────────────────
        $this->sectionCourse1 = SectionCourse::create([
            'section_user_id' => $sectionUser1->id, 'course_id' => $course->id,
            'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths Classe A',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        $this->sectionCourse2 = SectionCourse::create([
            'section_user_id' => $sectionUser2->id, 'course_id' => $course->id,
            'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths Classe B',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // ── Créneaux ─────────────────────────────────────────────────────────
        $this->scheduleA = Schedule::create([
            'section_course_id' => $this->sectionCourse1->id,
            'name' => 'Lundi S1', 'day_of_week' => 1,
            'start_time' => '10:00:00', 'end_time' => '12:00:00',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // Même section que A, même créneau — utilisé pour tester conflit section
        $this->scheduleB = Schedule::create([
            'section_course_id' => $this->sectionCourse1->id,
            'name' => 'Lundi S1 bis', 'day_of_week' => 1,
            'start_time' => '10:00:00', 'end_time' => '12:00:00',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // Section différente, même créneau — aucun conflit de section
        $this->scheduleC = Schedule::create([
            'section_course_id' => $this->sectionCourse2->id,
            'name' => 'Lundi S2', 'day_of_week' => 1,
            'start_time' => '10:00:00', 'end_time' => '12:00:00',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);

        // Non-chevauchant (08h–10h) — adjacent mais pas de chevauchement
        $this->scheduleD = Schedule::create([
            'section_course_id' => $this->sectionCourse1->id,
            'name' => 'Lundi matin', 'day_of_week' => 1,
            'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'is_active' => true, 'status' => 'A', 'created_by' => 1,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Crée un timesheet existant pour le scénario de base. */
    private function makeExistingTimesheet(
        UserSchoolRole $teacher,
        Schedule $schedule,
        Classroom $classroom,
        string $date = self::DATE,
    ): Timesheet {
        return Timesheet::create([
            'user_school_role_id' => $teacher->id,
            'schedule_id'         => $schedule->id,
            'subject_id'          => $this->subject->id,
            'classroom_id'        => $classroom->id,
            'date'                => $date,
            'hours_done'          => 2,
            'is_active'           => true,
            'created_by'          => 1,
        ]);
    }

    /** Retourne les erreurs de validation de la règle sur une date. */
    private function validate(
        int $scheduleId,
        int $teacherId,
        int $classroomId,
        string $date = self::DATE,
        ?int $ignoreId = null,
    ): array {
        $validator = Validator::make(
            ['date' => $date],
            ['date' => [new NoTimesheetConflict(
                scheduleId:       $scheduleId,
                userSchoolRoleId: $teacherId,
                classroomId:      $classroomId,
                ignoreId:         $ignoreId,
            )]]
        );

        return $validator->errors()->get('date');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1. Conflit de salle
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function salle_occupee_sur_meme_creneau_retourne_erreur(): void
    {
        // Arrange — salle1 déjà utilisée par le prof1 sur scheduleA le DATE
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — nouveau timesheet : prof2, scheduleA (même heure), même salle1
        $errors = $this->validate(
            scheduleId:   $this->scheduleA->id,
            teacherId:    $this->teacher2->id,
            classroomId:  $this->classroom1->id,
        );

        // Assert
        $this->assertNotEmpty($errors, 'La validation aurait dû échouer (conflit salle).');
        $this->assertStringContainsString('salle', mb_strtolower($errors[0]));
    }

    #[Test]
    public function salle_differente_sur_meme_creneau_passe_la_validation(): void
    {
        // Arrange — salle1 utilisée par prof1 sur scheduleA (section1)
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — prof2, scheduleC (section2 différente), salle2 différente
        // Isolation : aucun des trois conflits (prof ≠, salle ≠, section ≠)
        $errors = $this->validate(
            scheduleId:  $this->scheduleC->id,
            teacherId:   $this->teacher2->id,
            classroomId: $this->classroom2->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Pas de conflit attendu (salles différentes).');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. Conflit de professeur
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function professeur_deja_occupe_sur_meme_creneau_retourne_erreur(): void
    {
        // Arrange — prof1 déjà assigné sur scheduleA, salle1, le DATE
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — même prof1, scheduleB (même heure, même section), salle2 différente
        $errors = $this->validate(
            scheduleId:  $this->scheduleB->id,
            teacherId:   $this->teacher1->id,
            classroomId: $this->classroom2->id,
        );

        // Assert
        $this->assertNotEmpty($errors, 'La validation aurait dû échouer (conflit professeur).');
        $this->assertStringContainsString('professeur', mb_strtolower($errors[0]));
    }

    #[Test]
    public function professeur_different_sur_meme_creneau_passe_la_validation(): void
    {
        // Arrange — prof1 assigné sur scheduleA (section1), salle1
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — prof2 (différent), scheduleC (section2 ≠), salle2 ≠ → aucun conflit
        $errors = $this->validate(
            scheduleId:  $this->scheduleC->id,
            teacherId:   $this->teacher2->id,
            classroomId: $this->classroom2->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Pas de conflit attendu (professeur différent, section différente, salle différente).');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. Conflit de section
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function section_deja_en_cours_sur_meme_creneau_retourne_erreur(): void
    {
        // Arrange — scheduleA (section1) déjà occupé par prof1/salle1 le DATE
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — prof2, salle2 (OK pour prof+salle), scheduleB → section1 encore (conflit section)
        $errors = $this->validate(
            scheduleId:  $this->scheduleB->id,
            teacherId:   $this->teacher2->id,
            classroomId: $this->classroom2->id,
        );

        // Assert
        $this->assertNotEmpty($errors, 'La validation aurait dû échouer (conflit section).');
        $this->assertStringContainsString('section', mb_strtolower($errors[0]));
    }

    #[Test]
    public function section_differente_sur_meme_creneau_passe_la_validation(): void
    {
        // Arrange — scheduleA (section1) occupé le DATE
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — scheduleC (section2 différente), prof2, salle2
        $errors = $this->validate(
            scheduleId:  $this->scheduleC->id,
            teacherId:   $this->teacher2->id,
            classroomId: $this->classroom2->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Pas de conflit attendu (sections différentes).');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4. Disponibilité réelle — même professeur/salle/section, créneau horaire libre
    //
    // Les tests ci-dessus prouvent l'absence de conflit en faisant varier
    // plusieurs facteurs à la fois (prof ET section ET salle différents). Ceux-ci
    // isolent le seul facteur temps : professeur, salle et section identiques à
    // un timesheet existant, mais sur un créneau réellement libre.
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function professeur_salle_et_section_disponibles_sur_un_creneau_non_chevauchant(): void
    {
        // Arrange — prof1/salle1/section1 déjà occupés sur scheduleA (10h-12h)
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — même prof, même salle, même section (scheduleD → sectionCourse1),
        // mais sur un créneau 08h-10h qui ne chevauche pas 10h-12h
        $errors = $this->validate(
            scheduleId:  $this->scheduleD->id,
            teacherId:   $this->teacher1->id,
            classroomId: $this->classroom1->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Prof/salle/section libres sur un créneau non-chevauchant.');
    }

    #[Test]
    public function creneaux_adjacents_qui_se_touchent_sans_se_chevaucher_ne_sont_pas_en_conflit(): void
    {
        // Arrange — scheduleD (08h-10h) occupé par prof1/salle1
        $this->makeExistingTimesheet($this->teacher1, $this->scheduleD, $this->classroom1);

        // Act — scheduleA (10h-12h) : commence exactement quand scheduleD finit.
        // La formule de chevauchement (start1 < end2 AND end1 > start2) ne doit
        // pas considérer deux créneaux adjacents comme se chevauchant.
        $errors = $this->validate(
            scheduleId:  $this->scheduleA->id,
            teacherId:   $this->teacher1->id,
            classroomId: $this->classroom1->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Deux créneaux adjacents (10h pile) ne se chevauchent pas.');
    }

    #[Test]
    public function un_timesheet_ne_se_met_pas_en_conflit_avec_lui_meme_lors_dune_mise_a_jour(): void
    {
        // Arrange — timesheet existant sur scheduleA/prof1/salle1
        $timesheet = $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);

        // Act — on revalide les mêmes prof/salle/créneau en s'excluant soi-même
        // via ignoreId (cas d'une mise à jour qui ne change rien à l'horaire)
        $errors = $this->validate(
            scheduleId:  $this->scheduleA->id,
            teacherId:   $this->teacher1->id,
            classroomId: $this->classroom1->id,
            ignoreId:    $timesheet->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Un timesheet ne doit jamais entrer en conflit avec lui-même.');
    }

    #[Test]
    public function un_timesheet_supprime_ne_bloque_plus_le_creneau(): void
    {
        // Arrange — timesheet créé puis soft-deleted
        $timesheet = $this->makeExistingTimesheet($this->teacher1, $this->scheduleA, $this->classroom1);
        $timesheet->delete();

        // Act — un autre professeur peut désormais prendre ce créneau/cette salle
        $errors = $this->validate(
            scheduleId:  $this->scheduleA->id,
            teacherId:   $this->teacher2->id,
            classroomId: $this->classroom1->id,
        );

        // Assert
        $this->assertEmpty($errors, 'Un timesheet soft-deleted ne doit plus compter comme occupant le créneau.');
    }
}
