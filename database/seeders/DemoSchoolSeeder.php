<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\CantinePresence;
use App\Models\CantineRegistration;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Role;
use App\Models\School;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Construit UNE école de démo complète et cohérente (pas les données
 * éparpillées/aléatoires du DatabaseSeeder de base) : une classe de 20
 * élèves, plusieurs matières avec horaires réels, plusieurs semaines
 * d'historique (feuilles de temps + présences), cantine, notes sur 2
 * périodes. Un élève nommé et connu sert de compte de démo.
 *
 * Idempotent : School/Course/Section/Subject/Classroom/Schedule via
 * firstOrCreate sur (school_id, name) ou équivalent, Users via email
 * unique, Timesheet/Attendance/CantineRegistration/CantinePresence/Grade
 * via leurs contraintes uniques respectives. Sûr à relancer.
 */
class DemoSchoolSeeder extends Seeder
{
    private const SCHOOL_NAME = 'Lycée Démo Complet';
    private const SECTION_NAME = '3ème A — Démo';
    private const STUDENT_COUNT = 20;
    private const DEMO_STUDENT_EMAIL = 'etudiant.demo@school.com';
    private const DEMO_STUDENT_PASSWORD = 'EleveDemo2026!';
    private const WEEKS_OF_HISTORY = 3;

    public function run(): void
    {
        $school = $this->makeSchool();
        [$profMaths, $profFrancais] = $this->makeTeachers($school);
        $section = $this->makeSection($school);
        $classroom = $this->makeClassroom($school);

        $courses = $this->makeCoursesAndSubjects($school);
        $students = $this->makeStudents($school, $section);
        $demoStudent = $students->firstWhere('email', self::DEMO_STUDENT_EMAIL);

        $sectionCourses = $this->makeSectionCourses($section, $courses);
        $schedules = $this->makeSchedules($sectionCourses, $profMaths, $profFrancais);
        $timesheets = $this->makeTimesheets($schedules, $classroom);

        $this->makeAttendances($timesheets, $section);
        $this->makeCantine($school, $students);
        $this->makeGrades($students, $courses);

        $this->command?->info('');
        $this->command?->info('=== École de démo prête ===');
        $this->command?->info('École        : '.self::SCHOOL_NAME);
        $this->command?->info('Classe       : '.self::SECTION_NAME.' ('.self::STUDENT_COUNT.' élèves)');
        $this->command?->info('Connexion élève démo :');
        $this->command?->info('  email    : '.self::DEMO_STUDENT_EMAIL);
        $this->command?->info('  password : '.self::DEMO_STUDENT_PASSWORD);
        if ($demoStudent) {
            $this->command?->info('  user_id  : '.$demoStudent->id);
        }
    }

    private function makeSchool(): School
    {
        return School::firstOrCreate(
            ['name' => self::SCHOOL_NAME],
            [
                'reference' => 'DEMOFULL',
                'description' => 'École de démonstration générée pour valider toutes les fonctionnalités élève.',
                'status' => 'A',
                'is_active' => true,
                'cantine_enabled' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
    }

    /** @return array{0: UserSchoolRole, 1: UserSchoolRole} */
    private function makeTeachers(School $school): array
    {
        $profRole = Role::where('reference', 'PROF')->firstOrFail();

        $makeTeacher = function (string $email, string $first, string $last) use ($school, $profRole) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'firstname' => $first, 'lastname' => $last,
                    'password' => Hash::make('password'), 'email_verified_at' => now(),
                    'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                ]
            );

            return UserSchoolRole::firstOrCreate(
                ['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $profRole->id],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            );
        };

        return [
            $makeTeacher('prof.maths.demo@school.com', 'Sophie', 'Mathis'),
            $makeTeacher('prof.francais.demo@school.com', 'Marc', 'Lefèvre'),
        ];
    }

    private function makeSection(School $school): Section
    {
        return Section::firstOrCreate(
            ['school_id' => $school->id, 'name' => self::SECTION_NAME],
            ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
        );
    }

    private function makeClassroom(School $school): Classroom
    {
        return Classroom::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Salle 101 — Démo'],
            ['location' => 'Bâtiment A, 1er étage', 'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
        );
    }

    /** @return array<string, array{course: Course, subjects: \Illuminate\Support\Collection<int, Subject>}> */
    private function makeCoursesAndSubjects(School $school): array
    {
        $spec = [
            'Mathématiques' => ['Algèbre', 'Géométrie'],
            'Français' => ['Grammaire', 'Littérature'],
            'Histoire-Géographie' => ['Histoire', 'Géographie'],
        ];

        $result = [];
        foreach ($spec as $courseName => $subjectNames) {
            $course = Course::firstOrCreate(
                ['school_id' => $school->id, 'name' => $courseName],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            );

            $subjects = collect($subjectNames)->map(fn ($name) => Subject::firstOrCreate(
                ['course_id' => $course->id, 'name' => $name],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            ));

            $result[$courseName] = ['course' => $course, 'subjects' => $subjects];
        }

        return $result;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function makeStudents(School $school, Section $section): \Illuminate\Support\Collection
    {
        $eleveRole = Role::where('reference', 'ELEVE')->firstOrFail();
        $students = collect();

        // L'élève démo nommé, en premier, avec un mot de passe connu.
        $demoUser = User::updateOrCreate(
            ['email' => self::DEMO_STUDENT_EMAIL],
            [
                'firstname' => 'Camille', 'lastname' => 'Démo',
                'password' => Hash::make(self::DEMO_STUDENT_PASSWORD), 'email_verified_at' => now(),
                'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
            ]
        );
        $students->push($demoUser);

        for ($i = 1; $i < self::STUDENT_COUNT; $i++) {
            $students->push(User::factory()->create());
        }

        foreach ($students as $user) {
            $usr = UserSchoolRole::firstOrCreate(
                ['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            );

            SectionUserSchoolRole::firstOrCreate(
                ['section_id' => $section->id, 'user_school_role_id' => $usr->id],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            );
        }

        // Le compte élève démo a aussi default_school_id fixé pour arriver
        // directement sur la bonne école après connexion (pas de multi-école).
        $demoUser->update(['default_school_id' => $school->id]);

        return $students;
    }

    /** @return \Illuminate\Support\Collection<string, SectionCourse> */
    private function makeSectionCourses(Section $section, array $courses): \Illuminate\Support\Collection
    {
        $sectionUser = SectionUserSchoolRole::whereHas(
            'userschoolrole', fn ($q) => $q->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE'))
        )->where('section_id', $section->id)->first();
        // section_user_id sur SectionCourse pointe vers l'inscription qui "porte"
        // le cours dans cette section — n'importe quel élève de la section convient
        // (cf. pattern déjà utilisé par les autres seeders/tests de cette session).

        $result = collect();
        foreach ($courses as $courseName => ['course' => $course]) {
            $result[$courseName] = SectionCourse::firstOrCreate(
                ['section_user_id' => $sectionUser->id, 'course_id' => $course->id],
                [
                    'total_hours' => 60, 'hours_per_session' => 2, 'name' => "{$courseName} — ".self::SECTION_NAME,
                    'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                ]
            );
        }

        return $result;
    }

    /** @return \Illuminate\Support\Collection<int, Schedule> */
    private function makeSchedules(\Illuminate\Support\Collection $sectionCourses, UserSchoolRole $profMaths, UserSchoolRole $profFrancais): \Illuminate\Support\Collection
    {
        $spec = [
            ['course' => 'Mathématiques', 'day' => 1, 'start' => '08:00:00', 'end' => '10:00:00', 'name' => 'Maths — Lundi matin'],
            ['course' => 'Français', 'day' => 2, 'start' => '10:00:00', 'end' => '12:00:00', 'name' => 'Français — Mardi matin'],
            ['course' => 'Histoire-Géographie', 'day' => 3, 'start' => '14:00:00', 'end' => '16:00:00', 'name' => 'Histoire-Géo — Mercredi après-midi'],
            ['course' => 'Mathématiques', 'day' => 4, 'start' => '09:00:00', 'end' => '11:00:00', 'name' => 'Maths — Jeudi matin'],
            ['course' => 'Français', 'day' => 5, 'start' => '13:00:00', 'end' => '15:00:00', 'name' => 'Français — Vendredi après-midi'],
        ];

        return collect($spec)->map(fn ($s) => Schedule::firstOrCreate(
            ['section_course_id' => $sectionCourses[$s['course']]->id, 'name' => $s['name']],
            [
                'day_of_week' => $s['day'], 'start_time' => $s['start'], 'end_time' => $s['end'],
                'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
            ]
        ));
    }

    /** @return \Illuminate\Support\Collection<int, Timesheet> */
    private function makeTimesheets(\Illuminate\Support\Collection $schedules, Classroom $classroom): \Illuminate\Support\Collection
    {
        $timesheets = collect();
        $today = Carbon::now();

        foreach ($schedules as $schedule) {
            $sectionCourse = $schedule->sectionCourse;
            $subject = $sectionCourse->course->subjects->first() ?? Subject::where('course_id', $sectionCourse->course_id)->first();
            $teacher = $this->teacherForCourse($sectionCourse->course->name);

            if (! $subject || ! $teacher) {
                continue;
            }

            for ($week = self::WEEKS_OF_HISTORY; $week >= 0; $week--) {
                $date = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($week)->addDays($schedule->day_of_week - 1);

                if ($date->isFuture()) {
                    continue;
                }

                $timesheets->push(Timesheet::firstOrCreate(
                    ['schedule_id' => $schedule->id, 'date' => $date->toDateString()],
                    [
                        'user_school_role_id' => $teacher->id,
                        'subject_id' => $subject->id,
                        'classroom_id' => $classroom->id,
                        'hours_done' => 2,
                        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                    ]
                ));
            }
        }

        return $timesheets;
    }

    private function teacherForCourse(string $courseName): ?UserSchoolRole
    {
        static $cache = [];
        if (isset($cache[$courseName])) {
            return $cache[$courseName];
        }

        $email = $courseName === 'Français' ? 'prof.francais.demo@school.com' : 'prof.maths.demo@school.com';
        $user = User::where('email', $email)->first();

        return $cache[$courseName] = $user ? UserSchoolRole::where('user_id', $user->id)->first() : null;
    }

    private function makeAttendances(\Illuminate\Support\Collection $timesheets, Section $section): void
    {
        $students = SectionUserSchoolRole::where('section_id', $section->id)
            ->where('is_active', true)
            ->whereHas('userschoolrole', fn ($q) => $q->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
            ->get();

        foreach ($timesheets as $timesheet) {
            foreach ($students as $student) {
                $isPresent = fake()->boolean(88);

                Attendance::firstOrCreate(
                    ['timesheet_id' => $timesheet->id, 'section_user_id' => $student->id],
                    [
                        'is_present' => $isPresent,
                        'note' => $isPresent ? null : fake()->randomElement(['Malade', 'Justifié', 'Absence non justifiée']),
                        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                    ]
                );
            }
        }
    }

    private function makeCantine(School $school, \Illuminate\Support\Collection $students): void
    {
        $days = [1, 3, 5];

        foreach ($students as $index => $user) {
            if ($user->email !== self::DEMO_STUDENT_EMAIL && ! fake()->boolean(65)) {
                continue; // l'élève démo est toujours inscrit, les autres partiellement
            }

            $sectionUser = SectionUserSchoolRole::whereHas(
                'userschoolrole', fn ($q) => $q->where('user_id', $user->id)->where('school_id', $school->id)
            )->first();

            if (! $sectionUser) {
                continue;
            }

            foreach ($days as $day) {
                $registration = CantineRegistration::firstOrCreate(
                    ['section_user_id' => $sectionUser->id, 'day_of_week' => $day],
                    ['school_id' => $school->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
                );

                foreach ([1, 2] as $occurrence) {
                    $date = Carbon::now()->subWeeks($occurrence)->startOfWeek(Carbon::MONDAY)->addDays($day - 1);
                    $isPresent = fake()->boolean(90);

                    CantinePresence::firstOrCreate(
                        ['cantine_registration_id' => $registration->id, 'date' => $date->toDateString()],
                        [
                            'is_present' => $isPresent,
                            'note' => $isPresent ? null : 'Absent, non signalé',
                            'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                        ]
                    );
                }
            }
        }
    }

    private function makeGrades(\Illuminate\Support\Collection $students, array $courses): void
    {
        $allSubjects = collect($courses)->flatMap(fn ($c) => $c['subjects']);
        $periods = ['Trimestre 1', 'Trimestre 2'];

        foreach ($students as $user) {
            $sectionUser = SectionUserSchoolRole::whereHas(
                'userschoolrole', fn ($q) => $q->where('user_id', $user->id)
            )->first();

            if (! $sectionUser) {
                continue;
            }

            $isDemo = $user->email === self::DEMO_STUDENT_EMAIL;
            $subjectsToGrade = $isDemo ? $allSubjects : $allSubjects->random(min($allSubjects->count(), fake()->numberBetween(3, 6)));

            foreach ($subjectsToGrade as $subject) {
                foreach ($periods as $period) {
                    if (! $isDemo && ! fake()->boolean(70)) {
                        continue;
                    }

                    Grade::updateOrCreate(
                        ['section_user_id' => $sectionUser->id, 'subject_id' => $subject->id, 'period' => $period],
                        [
                            'grade' => fake()->randomFloat(2, 8, 19),
                            'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                        ]
                    );
                }
            }
        }
    }
}
