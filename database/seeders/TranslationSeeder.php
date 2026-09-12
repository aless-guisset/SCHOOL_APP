<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            // ── Navigation ─────────────────────────────────────────────
            'nav.dashboard'        => ['fr' => 'Tableau de bord',    'en' => 'Dashboard'],
            'nav.schools'          => ['fr' => 'Écoles',             'en' => 'Schools'],
            'nav.users'            => ['fr' => 'Utilisateurs',       'en' => 'Users'],
            'nav.roles'            => ['fr' => 'Rôles',              'en' => 'Roles'],
            'nav.sections'         => ['fr' => 'Sections',           'en' => 'Sections'],
            'nav.courses'          => ['fr' => 'Cours',              'en' => 'Courses'],
            'nav.subjects'         => ['fr' => 'Matières',           'en' => 'Subjects'],
            'nav.lessons'          => ['fr' => 'Leçons',             'en' => 'Lessons'],
            'nav.schedules'        => ['fr' => 'Horaires',           'en' => 'Schedules'],
            'nav.timesheets'       => ['fr' => 'Feuilles de temps',  'en' => 'Timesheets'],
            'nav.classrooms'       => ['fr' => 'Salles',             'en' => 'Classrooms'],
            'nav.resources'        => ['fr' => 'Ressources',         'en' => 'Resources'],
            'nav.translations'     => ['fr' => 'Traductions',        'en' => 'Translations'],
            'nav.logs'             => ['fr' => 'Logs',               'en' => 'Logs'],
            'nav.settings'         => ['fr' => 'Paramètres',         'en' => 'Settings'],
            'nav.logout'           => ['fr' => 'Déconnexion',        'en' => 'Logout'],

            // ── Actions communes ────────────────────────────────────────
            'action.create'        => ['fr' => 'Créer',              'en' => 'Create'],
            'action.edit'          => ['fr' => 'Modifier',           'en' => 'Edit'],
            'action.delete'        => ['fr' => 'Supprimer',          'en' => 'Delete'],
            'action.save'          => ['fr' => 'Enregistrer',        'en' => 'Save'],
            'action.cancel'        => ['fr' => 'Annuler',            'en' => 'Cancel'],
            'action.back'          => ['fr' => 'Retour',             'en' => 'Back'],
            'action.search'        => ['fr' => 'Rechercher',         'en' => 'Search'],
            'action.filter'        => ['fr' => 'Filtrer',            'en' => 'Filter'],
            'action.export'        => ['fr' => 'Exporter',           'en' => 'Export'],
            'action.submit'        => ['fr' => 'Soumettre',          'en' => 'Submit'],
            'action.confirm'       => ['fr' => 'Confirmer',          'en' => 'Confirm'],
            'action.update'        => ['fr' => 'Mettre à jour',      'en' => 'Update'],
            'action.clear_filters' => ['fr' => 'Effacer',            'en' => 'Clear'],

            // ── Labels communs ──────────────────────────────────────────
            'label.name'           => ['fr' => 'Nom',                'en' => 'Name'],
            'label.email'          => ['fr' => 'Email',              'en' => 'Email'],
            'label.phone'          => ['fr' => 'Téléphone',          'en' => 'Phone'],
            'label.address'        => ['fr' => 'Adresse',            'en' => 'Address'],
            'label.description'    => ['fr' => 'Description',        'en' => 'Description'],
            'label.status'         => ['fr' => 'Statut',             'en' => 'Status'],
            'label.active'         => ['fr' => 'Actif',              'en' => 'Active'],
            'label.inactive'       => ['fr' => 'Inactif',            'en' => 'Inactive'],
            'label.created_at'     => ['fr' => 'Créé le',            'en' => 'Created at'],
            'label.updated_at'     => ['fr' => 'Modifié le',         'en' => 'Updated at'],
            'label.actions'        => ['fr' => 'Actions',            'en' => 'Actions'],
            'label.reference'      => ['fr' => 'Référence',          'en' => 'Reference'],
            'label.role'           => ['fr' => 'Rôle',               'en' => 'Role'],
            'label.school'         => ['fr' => 'École',              'en' => 'School'],
            'label.section'        => ['fr' => 'Section',            'en' => 'Section'],
            'label.course'         => ['fr' => 'Cours',              'en' => 'Course'],
            'label.subject'        => ['fr' => 'Matière',            'en' => 'Subject'],
            'label.password'       => ['fr' => 'Mot de passe',       'en' => 'Password'],
            'label.firstname'      => ['fr' => 'Prénom',             'en' => 'First name'],
            'label.lastname'       => ['fr' => 'Nom',                'en' => 'Last name'],
            'label.total_hours'    => ['fr' => 'Heures totales',     'en' => 'Total hours'],
            'label.hours_session'  => ['fr' => 'Heures/séance',      'en' => 'Hours/session'],
            'label.day_of_week'    => ['fr' => 'Jour',               'en' => 'Day'],
            'label.start_time'     => ['fr' => 'Heure de début',     'en' => 'Start time'],
            'label.end_time'       => ['fr' => 'Heure de fin',       'en' => 'End time'],
            'label.date'           => ['fr' => 'Date',               'en' => 'Date'],
            'label.hours_done'     => ['fr' => 'Heures réalisées',   'en' => 'Hours done'],
            'label.location'       => ['fr' => 'Localisation',       'en' => 'Location'],
            'label.type'           => ['fr' => 'Type',               'en' => 'Type'],
            'label.key'            => ['fr' => 'Clé',                'en' => 'Key'],
            'label.language'       => ['fr' => 'Langue',             'en' => 'Language'],
            'label.value'          => ['fr' => 'Valeur',             'en' => 'Value'],
            'label.screen_name'    => ['fr' => 'Écran',              'en' => 'Screen'],

            // ── Jours de la semaine ─────────────────────────────────────
            'day.1'                => ['fr' => 'Lundi',              'en' => 'Monday'],
            'day.2'                => ['fr' => 'Mardi',              'en' => 'Tuesday'],
            'day.3'                => ['fr' => 'Mercredi',           'en' => 'Wednesday'],
            'day.4'                => ['fr' => 'Jeudi',              'en' => 'Thursday'],
            'day.5'                => ['fr' => 'Vendredi',           'en' => 'Friday'],
            'day.6'                => ['fr' => 'Samedi',             'en' => 'Saturday'],
            'day.7'                => ['fr' => 'Dimanche',           'en' => 'Sunday'],

            // ── Messages flash ──────────────────────────────────────────
            'flash.created'        => ['fr' => 'Créé avec succès.',  'en' => 'Created successfully.'],
            'flash.updated'        => ['fr' => 'Mis à jour.',        'en' => 'Updated.'],
            'flash.deleted'        => ['fr' => 'Supprimé.',          'en' => 'Deleted.'],
            'flash.error'          => ['fr' => 'Une erreur est survenue.', 'en' => 'An error occurred.'],

            // ── Dashboard ───────────────────────────────────────────────
            'dashboard.title'      => ['fr' => 'Tableau de bord',    'en' => 'Dashboard'],
            'dashboard.welcome'    => ['fr' => 'Bienvenue, :name',   'en' => 'Welcome, :name'],

            // ── Auth ────────────────────────────────────────────────────
            'auth.login'           => ['fr' => 'Se connecter',       'en' => 'Log in'],
            'auth.logout'          => ['fr' => 'Déconnexion',        'en' => 'Log out'],
            'auth.register'        => ['fr' => 'Créer un compte',    'en' => 'Register'],
            'auth.forgot_password' => ['fr' => 'Mot de passe oublié', 'en' => 'Forgot password'],

            // ── École ────────────────────────────────────────────────────
            'school.select.title'  => ['fr' => 'Choisir un établissement', 'en' => 'Choose an institution'],
            'school.create.title'  => ['fr' => 'Rejoindre un établissement', 'en' => 'Join an institution'],
            'school.pending'       => ['fr' => 'En attente d\'approbation', 'en' => 'Pending approval'],

            // ── Traductions (admin) ─────────────────────────────────────
            'translations.empty'        => ['fr' => 'Aucune traduction.',              'en' => 'No translations.'],
            'translations.new'          => ['fr' => 'Nouvelle traduction',             'en' => 'New translation'],
            'translations.edit_title'   => ['fr' => 'Modifier la traduction',          'en' => 'Edit translation'],
            'translations.confirm_delete' => ['fr' => 'Supprimer cette traduction ?',  'en' => 'Delete this translation?'],

            // ── Navigation (sidebar, nouvelles clés) ─────────────────────
            'nav.home'                  => ['fr' => 'Accueil',                         'en' => 'Home',                       'nl' => 'Start',                          'es' => 'Inicio'],
            'nav.schools_pending'       => ['fr' => 'Demandes en attente',              'en' => 'Pending requests',           'nl' => 'Openstaande aanvragen',          'es' => 'Solicitudes pendientes'],
            'nav.grades'                => ['fr' => 'Notes',                           'en' => 'Grades',                     'nl' => 'Cijfers',                        'es' => 'Notas'],
            'nav.medical_certificates'  => ['fr' => 'Certificats médicaux',             'en' => 'Medical certificates',       'nl' => 'Medische attesten',              'es' => 'Certificados médicos'],
            'nav.cantine'               => ['fr' => 'Cantine',                         'en' => 'Cafeteria',                  'nl' => 'Kantine',                        'es' => 'Cantina'],
            'nav.cantine_wallets'       => ['fr' => 'Soldes cantine',                   'en' => 'Cafeteria balances',         'nl' => 'Kantinesaldo\'s',                'es' => 'Saldos de cantina'],
            'nav.access_requests'       => ['fr' => 'Gestion des accès',                'en' => 'Access management',          'nl' => 'Toegangsbeheer',                 'es' => 'Gestión de accesos'],
            'nav.school_submit'         => ['fr' => 'Soumettre une école',              'en' => 'Submit a school',            'nl' => 'Een school indienen',            'es' => 'Enviar una escuela'],
            'nav.parent_links'          => ['fr' => 'Liens parent-élève',               'en' => 'Parent-student links',       'nl' => 'Ouder-leerlingkoppelingen',      'es' => 'Vínculos padre-alumno'],
            'nav.my_schedule'           => ['fr' => 'Mon horaire',                      'en' => 'My schedule',                'nl' => 'Mijn rooster',                   'es' => 'Mi horario'],
            'nav.my_courses'            => ['fr' => 'Mes cours',                        'en' => 'My courses',                 'nl' => 'Mijn lessen',                    'es' => 'Mis cursos'],
            'nav.my_sections'           => ['fr' => 'Mes sections',                     'en' => 'My sections',                'nl' => 'Mijn afdelingen',                'es' => 'Mis secciones'],
            'nav.my_grades'             => ['fr' => 'Mes notes',                        'en' => 'My grades',                  'nl' => 'Mijn cijfers',                   'es' => 'Mis notas'],
            'nav.give_access'           => ['fr' => 'Donner l\'accès',                  'en' => 'Give access',                'nl' => 'Toegang geven',                  'es' => 'Dar acceso'],
            'nav.schedule'              => ['fr' => 'Horaire',                         'en' => 'Schedule',                   'nl' => 'Rooster',                        'es' => 'Horario'],
            'nav.section.platform'      => ['fr' => 'Plateforme',                       'en' => 'Platform',                   'nl' => 'Platform',                       'es' => 'Plataforma'],
            'nav.section.system'        => ['fr' => 'Système',                          'en' => 'System',                     'nl' => 'Systeem',                        'es' => 'Sistema'],
            'nav.section.school'        => ['fr' => 'École',                           'en' => 'School',                     'nl' => 'School',                         'es' => 'Escuela'],
            'nav.section.planning'      => ['fr' => 'Planification',                    'en' => 'Planning',                   'nl' => 'Planning',                       'es' => 'Planificación'],
            'nav.section.school_management' => ['fr' => 'Gestion école',                'en' => 'School management',          'nl' => 'Schoolbeheer',                   'es' => 'Gestión escolar'],
            'nav.section.my_students'   => ['fr' => 'Mes élèves',                       'en' => 'My students',                'nl' => 'Mijn leerlingen',                'es' => 'Mis alumnos'],
            'nav.section.my_space'      => ['fr' => 'Mon espace',                       'en' => 'My space',                   'nl' => 'Mijn ruimte',                    'es' => 'Mi espacio'],
            'nav.section.tracking'      => ['fr' => 'Suivi',                           'en' => 'Tracking',                   'nl' => 'Opvolging',                      'es' => 'Seguimiento'],
            'nav.section.my_children'   => ['fr' => 'Mes enfants',                      'en' => 'My children',                'nl' => 'Mijn kinderen',                  'es' => 'Mis hijos'],
            'nav.join_other_school'     => ['fr' => '+ Rejoindre un autre établissement', 'en' => '+ Join another institution', 'nl' => '+ Een andere instelling toevoegen', 'es' => '+ Unirse a otro centro'],
            'action.set_default'        => ['fr' => 'Définir par défaut',               'en' => 'Set as default',             'nl' => 'Als standaard instellen',        'es' => 'Establecer como predeterminado'],
            'label.default_school'      => ['fr' => 'École par défaut',                 'en' => 'Default school',             'nl' => 'Standaardschool',                'es' => 'Escuela predeterminada'],
            'label.default'             => ['fr' => 'Par défaut',                       'en' => 'Default',                    'nl' => 'Standaard',                      'es' => 'Predeterminado'],

            // ── Page d'accueil (Welcome.vue) ─────────────────────────────
            'welcome.head_title'          => ['fr' => 'SchoolApp — Gestion scolaire simplifiée', 'en' => 'SchoolApp — School management made simple', 'nl' => 'SchoolApp — Schoolbeheer vereenvoudigd', 'es' => 'SchoolApp — Gestión escolar simplificada'],
            'welcome.download_tooltip'    => ['fr' => 'Télécharger l\'application — pas disponible pour le moment', 'en' => 'Download the app — not available right now', 'nl' => 'App downloaden — momenteel niet beschikbaar', 'es' => 'Descargar la aplicación — no disponible por el momento'],
            'welcome.theme_light'         => ['fr' => 'Passer en thème clair', 'en' => 'Switch to light theme', 'nl' => 'Overschakelen naar lichte modus', 'es' => 'Cambiar a tema claro'],
            'welcome.theme_dark'          => ['fr' => 'Passer en thème sombre', 'en' => 'Switch to dark theme', 'nl' => 'Overschakelen naar donkere modus', 'es' => 'Cambiar a tema oscuro'],
            'welcome.admin_panel'         => ['fr' => 'Admin panel', 'en' => 'Admin panel', 'nl' => 'Adminpaneel', 'es' => 'Panel de administración'],
            'welcome.access_app'          => ['fr' => 'Accéder à l\'application', 'en' => 'Access the app', 'nl' => 'Naar de app', 'es' => 'Acceder a la aplicación'],
            'welcome.create_school'       => ['fr' => 'Créer un établissement', 'en' => 'Create an institution', 'nl' => 'Een instelling aanmaken', 'es' => 'Crear un centro'],
            'welcome.create_school_short' => ['fr' => 'Créer', 'en' => 'Create', 'nl' => 'Aanmaken', 'es' => 'Crear'],
            'welcome.badge'               => ['fr' => 'Plateforme de gestion scolaire', 'en' => 'School management platform', 'nl' => 'Platform voor schoolbeheer', 'es' => 'Plataforma de gestión escolar'],
            'welcome.hero_title_line1'    => ['fr' => 'Gérez votre école', 'en' => 'Manage your school', 'nl' => 'Beheer uw school', 'es' => 'Gestione su escuela'],
            'welcome.hero_title_line2'    => ['fr' => 'simplement.', 'en' => 'simply.', 'nl' => 'eenvoudig.', 'es' => 'de forma sencilla.'],
            'welcome.hero_sub'            => ['fr' => 'SchoolApp centralise les présences, horaires, cours et salles de classe en une seule plateforme accessible à toutes les écoles.', 'en' => 'SchoolApp centralizes attendance, schedules, courses and classrooms in a single platform accessible to every school.', 'nl' => 'SchoolApp centraliseert aanwezigheden, roosters, lessen en klaslokalen in één platform, toegankelijk voor elke school.', 'es' => 'SchoolApp centraliza la asistencia, los horarios, los cursos y las aulas en una sola plataforma accesible para todas las escuelas.'],
            'welcome.view_modules'        => ['fr' => 'Voir les modules', 'en' => 'View modules', 'nl' => 'Modules bekijken', 'es' => 'Ver los módulos'],
            'welcome.modules_eyebrow'     => ['fr' => 'Modules', 'en' => 'Modules', 'nl' => 'Modules', 'es' => 'Módulos'],
            'welcome.modules_title'       => ['fr' => 'Tout ce dont votre école a besoin', 'en' => 'Everything your school needs', 'nl' => 'Alles wat uw school nodig heeft', 'es' => 'Todo lo que su escuela necesita'],
            'welcome.modules_sub'         => ['fr' => 'Des modules pensés pour couvrir chaque aspect de la gestion scolaire.', 'en' => 'Modules designed to cover every aspect of school management.', 'nl' => 'Modules ontworpen om elk aspect van schoolbeheer te dekken.', 'es' => 'Módulos diseñados para cubrir todos los aspectos de la gestión escolar.'],
            'welcome.module_attendance_title' => ['fr' => 'Fiches de présence', 'en' => 'Attendance records', 'nl' => 'Aanwezigheidsregistratie', 'es' => 'Fichas de asistencia'],
            'welcome.module_attendance_desc'  => ['fr' => 'Enregistrez les présences par session et suivez les heures réalisées par rapport au plafond défini.', 'en' => 'Record attendance per session and track hours completed against the defined cap.', 'nl' => 'Registreer aanwezigheden per sessie en volg de gerealiseerde uren op ten opzichte van het vastgestelde maximum.', 'es' => 'Registre la asistencia por sesión y controle las horas realizadas frente al límite definido.'],
            'welcome.module_schedules_desc'    => ['fr' => 'Créez des créneaux hebdomadaires et gérez automatiquement les plafonds d\'heures par cours.', 'en' => 'Create weekly time slots and automatically manage hour caps per course.', 'nl' => 'Maak wekelijkse tijdsloten en beheer automatisch de urenlimieten per cursus.', 'es' => 'Cree franjas horarias semanales y gestione automáticamente los límites de horas por curso.'],
            'welcome.module_courses_title'     => ['fr' => 'Cours & matières', 'en' => 'Courses & subjects', 'nl' => 'Cursussen & vakken', 'es' => 'Cursos y materias'],
            'welcome.module_courses_desc'      => ['fr' => 'Organisez vos cours et matières par section, niveau et enseignant assigné.', 'en' => 'Organize your courses and subjects by section, level and assigned teacher.', 'nl' => 'Organiseer uw cursussen en vakken per afdeling, niveau en toegewezen leerkracht.', 'es' => 'Organice sus cursos y materias por sección, nivel y docente asignado.'],
            'welcome.module_sections_title'    => ['fr' => 'Sections & élèves', 'en' => 'Sections & students', 'nl' => 'Afdelingen & leerlingen', 'es' => 'Secciones y alumnos'],
            'welcome.module_sections_desc'     => ['fr' => 'Regroupez les élèves par section et attribuez des rôles précis à chaque intervenant.', 'en' => 'Group students by section and assign precise roles to each participant.', 'nl' => 'Groepeer leerlingen per afdeling en wijs specifieke rollen toe aan elke betrokkene.', 'es' => 'Agrupe a los alumnos por sección y asigne roles precisos a cada participante.'],
            'welcome.module_classrooms_title'  => ['fr' => 'Salles de classe', 'en' => 'Classrooms', 'nl' => 'Klaslokalen', 'es' => 'Aulas'],
            'welcome.module_classrooms_desc'   => ['fr' => 'Gérez vos espaces, leur localisation et leur attribution aux sessions planifiées.', 'en' => 'Manage your spaces, their location and their assignment to scheduled sessions.', 'nl' => 'Beheer uw ruimtes, hun locatie en hun toewijzing aan geplande sessies.', 'es' => 'Gestione sus espacios, su ubicación y su asignación a las sesiones programadas.'],
            'welcome.module_roles_title'       => ['fr' => 'Rôles & accès', 'en' => 'Roles & access', 'nl' => 'Rollen & toegang', 'es' => 'Roles y accesos'],
            'welcome.module_roles_desc'        => ['fr' => 'Contrôlez les accès par école avec des rôles distincts : admin, enseignant, élève.', 'en' => 'Control access per school with distinct roles: admin, teacher, student.', 'nl' => 'Beheer de toegang per school met verschillende rollen: beheerder, leerkracht, leerling.', 'es' => 'Controle el acceso por escuela con roles diferenciados: administrador, docente, alumno.'],
            'welcome.how_it_works_eyebrow' => ['fr' => 'Comment ça marche', 'en' => 'How it works', 'nl' => 'Hoe het werkt', 'es' => 'Cómo funciona'],
            'welcome.steps_title'         => ['fr' => 'Démarrez en 4 étapes', 'en' => 'Get started in 4 steps', 'nl' => 'Start in 4 stappen', 'es' => 'Comience en 4 pasos'],
            'welcome.step1_title'         => ['fr' => 'Créez votre compte', 'en' => 'Create your account', 'nl' => 'Maak uw account aan', 'es' => 'Cree su cuenta'],
            'welcome.step1_desc'          => ['fr' => 'Inscrivez-vous gratuitement avec votre adresse e-mail.', 'en' => 'Sign up for free with your email address.', 'nl' => 'Registreer gratis met uw e-mailadres.', 'es' => 'Regístrese gratis con su dirección de correo electrónico.'],
            'welcome.step2_title'         => ['fr' => 'Soumettez votre école', 'en' => 'Submit your school', 'nl' => 'Dien uw school in', 'es' => 'Envíe su escuela'],
            'welcome.step2_desc'          => ['fr' => 'Remplissez le formulaire. Notre équipe valide votre demande.', 'en' => 'Fill out the form. Our team reviews your request.', 'nl' => 'Vul het formulier in. Ons team beoordeelt uw aanvraag.', 'es' => 'Complete el formulario. Nuestro equipo valida su solicitud.'],
            'welcome.step3_title'         => ['fr' => 'Configurez vos modules', 'en' => 'Configure your modules', 'nl' => 'Configureer uw modules', 'es' => 'Configure sus módulos'],
            'welcome.step3_desc'          => ['fr' => 'Ajoutez sections, cours, horaires et assignez les rôles.', 'en' => 'Add sections, courses, schedules and assign roles.', 'nl' => 'Voeg afdelingen, cursussen, roosters toe en wijs rollen toe.', 'es' => 'Añada secciones, cursos, horarios y asigne roles.'],
            'welcome.step4_title'         => ['fr' => 'Gérez au quotidien', 'en' => 'Manage day to day', 'nl' => 'Beheer dagelijks', 'es' => 'Gestione día a día'],
            'welcome.step4_desc'          => ['fr' => 'Suivez les présences et horaires en temps réel.', 'en' => 'Track attendance and schedules in real time.', 'nl' => 'Volg aanwezigheden en roosters in realtime.', 'es' => 'Siga la asistencia y los horarios en tiempo real.'],
            'welcome.cta_title'           => ['fr' => 'Prêt à moderniser votre école ?', 'en' => 'Ready to modernize your school?', 'nl' => 'Klaar om uw school te moderniseren?', 'es' => '¿Listo para modernizar su escuela?'],
            'welcome.cta_sub'             => ['fr' => 'Rejoignez les écoles qui font confiance à SchoolApp.', 'en' => 'Join the schools that trust SchoolApp.', 'nl' => 'Sluit u aan bij de scholen die op SchoolApp vertrouwen.', 'es' => 'Únase a las escuelas que confían en SchoolApp.'],
            'welcome.footer_copyright'    => ['fr' => '© 2025 SchoolApp', 'en' => '© 2025 SchoolApp', 'nl' => '© 2025 SchoolApp', 'es' => '© 2025 SchoolApp'],
        ];

        foreach ($translations as $key => $locales) {
            foreach ($locales as $locale => $value) {
                Translation::updateOrCreate(
                    ['tag_key' => $key, 'language_code' => $locale],
                    [
                        'translated_value' => $value,
                        'is_active'        => true,
                        'created_by'       => 1,
                        'updated_by'       => 1,
                    ]
                );
            }
        }
    }
}
