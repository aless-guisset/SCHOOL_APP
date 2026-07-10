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
