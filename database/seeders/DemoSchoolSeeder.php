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
use App\Notifications\TimesheetAssignedNotification;
use App\Notifications\TimesheetCancelledNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Construit UNE école de démo complète et cohérente (pas les données
 * éparpillées/aléatoires du DatabaseSeeder de base) : une classe de 20
 * élèves, une semaine type entièrement remplie de 8h à 16h (pause déjeuner
 * 12h-13h), 3 professeurs sur 6 matières, plusieurs semaines d'historique
 * (feuilles de temps + présences), cantine, notes sur 2 périodes, et des
 * notifications réelles pour l'élève de démo. Un élève nommé et connu sert
 * de compte de démo.
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

    /** Journée type 8h-16h, pause déjeuner 12h-13h. */
    private const TIMETABLE = [
        // day => [[start, end, course], ...]
        1 => [['08:00:00', '10:00:00', 'Mathématiques'], ['10:00:00', '12:00:00', 'Français'], ['13:00:00', '15:00:00', 'Histoire-Géographie'], ['15:00:00', '16:00:00', 'Anglais']],
        2 => [['08:00:00', '10:00:00', 'Sciences'], ['10:00:00', '12:00:00', 'Mathématiques'], ['13:00:00', '15:00:00', 'EPS'], ['15:00:00', '16:00:00', 'Français']],
        3 => [['08:00:00', '10:00:00', 'Français'], ['10:00:00', '12:00:00', 'Histoire-Géographie'], ['13:00:00', '15:00:00', 'Anglais'], ['15:00:00', '16:00:00', 'Sciences']],
        4 => [['08:00:00', '10:00:00', 'Mathématiques'], ['10:00:00', '12:00:00', 'Sciences'], ['13:00:00', '15:00:00', 'Français'], ['15:00:00', '16:00:00', 'EPS']],
        5 => [['08:00:00', '10:00:00', 'Histoire-Géographie'], ['10:00:00', '12:00:00', 'Anglais'], ['13:00:00', '15:00:00', 'Mathématiques'], ['15:00:00', '16:00:00', 'Sciences']],
    ];

    private const DAY_NAMES = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi'];

    private static ?string $sharedPasswordHash = null;

    /**
     * bcrypt (~300-500ms/appel) domine largement le temps d'exécution de ce
     * seeder dès qu'on hashe le même mot de passe pour chaque compte de
     * remplissage (constaté : 9.4s sur 20 appels lors du profilage) — un
     * seul hash est calculé et réutilisé pour tous les comptes qui partagent
     * le mot de passe générique 'password' (élèves de remplissage, profs).
     */
    private static function sharedPasswordHash(): string
    {
        return self::$sharedPasswordHash ??= Hash::make('password');
    }

    public function run(): void
    {
        // Étapes loguées au fil de l'eau : la commande ssh d'un hébergeur
        // distant peut couper une connexion restée silencieuse plusieurs
        // secondes (constaté sur Railway) — un seeder qui ne produit aucune
        // sortie avant sa toute dernière ligne y est particulièrement
        // exposé (contrairement à DatabaseSeeder, qui log chaque sous-seeder).
        $school = $this->makeSchool();
        $this->command?->info('École créée/trouvée.');

        $teachers = $this->makeTeachers($school);
        $this->command?->info('Professeurs prêts.');

        $section = $this->makeSection($school);
        $classrooms = $this->makeClassrooms($school);
        $this->command?->info('Section et salles prêtes.');

        $courses = $this->makeCoursesAndSubjects($school);
        $this->command?->info('Cours et matières prêts.');

        $students = $this->makeStudents($school, $section);
        $demoUser = $students->firstWhere('email', self::DEMO_STUDENT_EMAIL);
        $this->command?->info('20 élèves prêts.');

        $sectionCourses = $this->makeSectionCourses($section, $courses);
        $schedules = $this->makeSchedules($sectionCourses, $classrooms, $teachers);
        $this->command?->info('Emploi du temps (8h-16h, 5 jours) prêt — séances futures générées automatiquement jusqu\'au '.$school->year_end_date->toDateString().'.');

        $timesheets = $this->makeTimesheets($schedules);
        $this->command?->info('Historique de feuilles de temps ('.$timesheets->count().') prêt.');

        $this->makeAttendances($timesheets, $section);
        $this->command?->info('Présences prêtes.');

        $this->makeCantine($school, $students);
        $this->command?->info('Cantine prête.');

        $this->makeGrades($students, $courses);
        $this->command?->info('Notes prêtes.');

        $this->makeNotifications($demoUser, $timesheets, $teachers);
        $this->command?->info('Notifications prêtes.');

        $this->command?->info('');
        $this->command?->info('=== École de démo prête ===');
        $this->command?->info('École        : '.self::SCHOOL_NAME);
        $this->command?->info('Classe       : '.self::SECTION_NAME.' ('.self::STUDENT_COUNT.' élèves)');
        $this->command?->info('Semaine type : 8h-16h, pause 12h-13h, 6 matières, 3 profs');
        $this->command?->info('Connexion élève démo :');
        $this->command?->info('  email    : '.self::DEMO_STUDENT_EMAIL);
        $this->command?->info('  password : '.self::DEMO_STUDENT_PASSWORD);
        if ($demoUser) {
            $this->command?->info('  user_id  : '.$demoUser->id);
        }
    }

    private function makeSchool(): School
    {
        $today = Carbon::now();
        // Prochain 30 juin (année scolaire nord : si on est après juin, viser l'année suivante).
        $yearEnd = $today->month > 6
            ? Carbon::create($today->year + 1, 6, 30)
            : Carbon::create($today->year, 6, 30);

        return School::updateOrCreate(
            ['name' => self::SCHOOL_NAME],
            [
                'reference' => 'DEMOFULL',
                'description' => 'École de démonstration générée pour valider toutes les fonctionnalités élève.',
                'status' => 'A',
                'is_active' => true,
                'cantine_enabled' => true,
                'year_end_date' => $yearEnd->toDateString(),
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
    }

    /** @return array<string, UserSchoolRole> matière => enseignant */
    private function makeTeachers(School $school): array
    {
        $profRole = Role::where('reference', 'PROF')->firstOrFail();

        $makeTeacher = function (string $email, string $first, string $last) use ($school, $profRole) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'firstname' => $first, 'lastname' => $last,
                    'password' => self::sharedPasswordHash(), 'email_verified_at' => now(),
                    'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                ]
            );

            return UserSchoolRole::firstOrCreate(
                ['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $profRole->id],
                ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
            );
        };

        $sophie = $makeTeacher('prof.maths.demo@school.com', 'Sophie', 'Mathis');
        $marc = $makeTeacher('prof.francais.demo@school.com', 'Marc', 'Lefèvre');
        $julie = $makeTeacher('prof.histoire.demo@school.com', 'Julie', 'Bernard');

        return [
            'Mathématiques' => $sophie,
            'Sciences' => $sophie,
            'Français' => $marc,
            'Anglais' => $marc,
            'Histoire-Géographie' => $julie,
            'EPS' => $julie,
        ];
    }

    private function makeSection(School $school): Section
    {
        return Section::firstOrCreate(
            ['school_id' => $school->id, 'name' => self::SECTION_NAME],
            ['status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
        );
    }

    /** @return array<string, Classroom> */
    private function makeClassrooms(School $school): array
    {
        $default = Classroom::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Salle 101 — Démo'],
            ['location' => 'Bâtiment A, 1er étage', 'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
        );
        $gym = Classroom::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Gymnase — Démo'],
            ['location' => 'Bâtiment B', 'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1]
        );

        return ['default' => $default, 'EPS' => $gym];
    }

    /** @return array<string, array{course: Course, subjects: \Illuminate\Support\Collection<int, Subject>}> */
    private function makeCoursesAndSubjects(School $school): array
    {
        $spec = [
            'Mathématiques' => ['Algèbre', 'Géométrie'],
            'Français' => ['Grammaire', 'Littérature'],
            'Histoire-Géographie' => ['Histoire', 'Géographie'],
            'Sciences' => ['Physique', 'Chimie'],
            'Anglais' => ['Anglais'],
            'EPS' => ['Éducation physique'],
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

        $demoUser = User::updateOrCreate(
            ['email' => self::DEMO_STUDENT_EMAIL],
            [
                'firstname' => 'Camille', 'lastname' => 'Démo',
                'password' => Hash::make(self::DEMO_STUDENT_PASSWORD), 'email_verified_at' => now(),
                'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
            ]
        );
        $students->push($demoUser);

        // Emails déterministes : firstOrCreate empêche toute création en
        // double si le seeder est relancé (contrairement à un email aléatoire
        // via factory(), qui créerait 19 nouveaux élèves à chaque exécution).
        for ($i = 2; $i <= self::STUDENT_COUNT; $i++) {
            $students->push(User::firstOrCreate(
                ['email' => sprintf('eleve.demo.%02d@school.com', $i)],
                [
                    'firstname' => fake()->firstName(), 'lastname' => fake()->lastName(),
                    'password' => self::sharedPasswordHash(), 'email_verified_at' => now(),
                    'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                ]
            ));
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

        $demoUser->update(['default_school_id' => $school->id]);

        return $students;
    }

    /** @return \Illuminate\Support\Collection<string, SectionCourse> */
    private function makeSectionCourses(Section $section, array $courses): \Illuminate\Support\Collection
    {
        $sectionUser = SectionUserSchoolRole::whereHas(
            'userschoolrole', fn ($q) => $q->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE'))
        )->where('section_id', $section->id)->first();

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
    private function makeSchedules(\Illuminate\Support\Collection $sectionCourses, array $classrooms, array $teachers): \Illuminate\Support\Collection
    {
        $this->cleanupStaleSchedules($sectionCourses);

        $schedules = collect();

        foreach (self::TIMETABLE as $day => $slots) {
            foreach ($slots as [$start, $end, $courseName]) {
                $sectionCourse = $sectionCourses[$courseName];
                $teacher = $teachers[$courseName] ?? null;
                $classroom = $classrooms[$courseName] ?? $classrooms['default'];
                // ->first() (pas ->random()) : un choix stable évite de déclencher
                // une resynchronisation à chaque relance du seeder pour une matière
                // qui n'a en réalité pas changé.
                $subject = $sectionCourse->course->subjects->first();

                // Clé d'identité stable (section_course_id, day_of_week,
                // start_time) plutôt que `name` : le libellé a changé entre
                // deux versions de ce seeder, ce qui aurait recréé des
                // créneaux en double à chaque évolution du format du nom.
                $schedules->push(Schedule::updateOrCreate(
                    [
                        'section_course_id' => $sectionCourse->id,
                        'day_of_week' => $day,
                        'start_time' => $start,
                    ],
                    [
                        'name' => self::DAY_NAMES[$day].' '.substr($start, 0, 5).'-'.substr($end, 0, 5),
                        'end_time' => $end,
                        'user_school_role_id' => $teacher?->id,
                        'subject_id' => $subject?->id,
                        'classroom_id' => $classroom?->id,
                        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                    ]
                ));
            }
        }

        return $schedules;
    }

    /**
     * Supprime tout Schedule d'une version précédente de ce seeder qui ne
     * correspond plus à la grille horaire actuelle (identifié par
     * section_course_id + day_of_week + start_time) — sans ce nettoyage,
     * un ancien créneau (ex : "Lundi 08:00-10:00" vs l'ancien format "Maths
     * — Lundi matin" à 08:00-10:00 mais un autre jour) reste en base et
     * chevauche le nouveau. forceDelete() (pas un simple soft delete) pour
     * que la contrainte FK cascadeOnDelete() nettoie aussi les Timesheets/
     * Attendances qui en dépendaient — acceptable ici car ce sont des
     * données de démo entièrement régénérées par ce seeder, jamais de
     * données réelles d'utilisateur.
     */
    private function cleanupStaleSchedules(\Illuminate\Support\Collection $sectionCourses): void
    {
        $validKeys = collect();
        foreach (self::TIMETABLE as $day => $slots) {
            foreach ($slots as [$start, , $courseName]) {
                $validKeys->push($sectionCourses[$courseName]->id.'|'.$day.'|'.$start);
            }
        }

        $sectionCourseIds = $sectionCourses->pluck('id');

        $stale = Schedule::withTrashed()
            ->whereIn('section_course_id', $sectionCourseIds)
            ->get()
            ->reject(fn (Schedule $s) => $validKeys->contains($s->section_course_id.'|'.$s->day_of_week.'|'.$s->start_time));

        foreach ($stale as $schedule) {
            $schedule->forceDelete();
        }

        if ($stale->isNotEmpty()) {
            $this->command?->info("Nettoyage : {$stale->count()} ancien(s) créneau(x) obsolète(s) supprimé(s).");
        }
    }

    /** @return \Illuminate\Support\Collection<int, Timesheet> */
    private function makeTimesheets(\Illuminate\Support\Collection $schedules): \Illuminate\Support\Collection
    {
        $timesheets = collect();
        $today = Carbon::now();

        foreach ($schedules as $schedule) {
            if (! $schedule->user_school_role_id || ! $schedule->subject_id || ! $schedule->classroom_id) {
                continue;
            }

            // Semaines passées uniquement : la semaine courante et les suivantes
            // sont déjà couvertes par la génération automatique (ScheduleObserver),
            // qui part d'aujourd'hui — ce backfill ne doit pas la dupliquer.
            for ($week = self::WEEKS_OF_HISTORY; $week >= 1; $week--) {
                $date = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($week)->addDays($schedule->day_of_week - 1);

                $timesheets->push(Timesheet::firstOrCreate(
                    ['schedule_id' => $schedule->id, 'date' => $date->toDateString()],
                    [
                        'user_school_role_id' => $schedule->user_school_role_id,
                        'subject_id' => $schedule->subject_id,
                        'classroom_id' => $schedule->classroom_id,
                        'hours_done' => 2,
                        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                    ]
                ));
            }
        }

        return $timesheets;
    }

    private function makeAttendances(\Illuminate\Support\Collection $timesheets, Section $section): void
    {
        $students = SectionUserSchoolRole::where('section_id', $section->id)
            ->where('is_active', true)
            ->whereHas('userschoolrole', fn ($q) => $q->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
            ->get();

        // Volume élevé (timesheets × élèves, ~1600 lignes) : insertOrIgnore()
        // en lot plutôt que firstOrCreate() ligne par ligne, pour rester
        // rapide et éviter une longue phase silencieuse (une session ssh
        // distante peut couper une connexion restée inactive trop longtemps).
        $now = now();
        $rows = [];

        foreach ($timesheets as $timesheet) {
            foreach ($students as $student) {
                $isPresent = fake()->boolean(88);

                $rows[] = [
                    'timesheet_id' => $timesheet->id,
                    'section_user_id' => $student->id,
                    'is_present' => $isPresent,
                    'note' => $isPresent ? null : fake()->randomElement(['Malade', 'Justifié', 'Absence non justifiée']),
                    'status' => 'A', 'is_active' => true,
                    'created_by' => 1, 'updated_by' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::insertOrIgnore($chunk);
        }
    }

    private function makeCantine(School $school, \Illuminate\Support\Collection $students): void
    {
        $days = [1, 3, 5];

        foreach ($students as $user) {
            if ($user->email !== self::DEMO_STUDENT_EMAIL && ! fake()->boolean(65)) {
                continue;
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

    /**
     * Crée directement les lignes de la table `notifications` (canal database
     * de TimesheetAssignedNotification/TimesheetCancelledNotification) sans
     * passer par ->notify()/Notification::send() — évite toute tentative
     * d'envoi mail réel pendant un seeding (les deux notifications
     * implémentent aussi le canal 'mail').
     *
     * @param  array<string, UserSchoolRole>  $teachers
     */
    private function makeNotifications(?User $demoUser, \Illuminate\Support\Collection $timesheets, array $teachers): void
    {
        if (! $demoUser || $timesheets->isEmpty()) {
            return;
        }

        $demoUsr = UserSchoolRole::where('user_id', $demoUser->id)->first();
        $ownTimesheets = $timesheets->filter(fn ($ts) => $ts->user_school_role_id === $demoUsr?->id);
        // L'élève démo n'est jamais le professeur d'un timesheet (rôle ELEVE) : ces
        // notifications sont donc générées indépendamment de $ownTimesheets, sur un
        // échantillon des derniers timesheets de la classe, comme si l'élève démo
        // suivait l'activité de son emploi du temps.
        $recent = $timesheets->sortByDesc('date')->take(5)->values();
        $sender = collect($teachers)->first();
        $senderUser = $sender ? User::find($sender->user_id) : null;

        if (! $senderUser || $recent->isEmpty()) {
            return;
        }

        if ($demoUser->notifications()->count() > 0) {
            return; // déjà seedé
        }

        foreach ($recent as $i => $timesheet) {
            $isCancelled = $i === 0; // la plus récente simule une annulation
            $notification = $isCancelled
                ? new TimesheetCancelledNotification($timesheet->date, $senderUser)
                : new TimesheetAssignedNotification($timesheet, $senderUser);

            $demoUser->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => get_class($notification),
                'data' => $notification->toArray($demoUser),
                'read_at' => $i >= 3 ? null : now()->subHours($i + 1), // les 2 plus récentes non lues
                'created_at' => now()->subHours($i + 1),
                'updated_at' => now()->subHours($i + 1),
            ]);
        }
    }
}
