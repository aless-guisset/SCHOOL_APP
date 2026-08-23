# Roadmap — School App

## ✅ Fait
- Phase 1 : Middleware école, flux login, sidebar dynamique, pages school/Select + Create
- Phase 2 : Controllers Inertia, traductions DB, composables, Dashboard, pages CRUD de base
- Migrations individuelles par table (16 fichiers)
- Auth firstname/lastname, UserInfo, Register, Profile corrigés
- School Panel avec stats + modules par rôle + prochains créneaux
- Textarea UI component, DataTable, PageHeader, FlashMessage

## ✅ Complété (sessions précédentes)
- Page approbation écoles (`Pending.vue` + `approve`/`reject`)
- Détection conflits d'horaire (`NoTimesheetConflict` rule)
- Pages CRUD manquantes (Users, Classrooms, Resources, Subjects, Lessons, Timesheets)
- Logs d'activité (`ActivityObserver` sur 12 modèles + `ActivityLogs/Index.vue`)
- Duplication de planning (`DuplicatePlanningController` + dialog Schedules/Index)
- Dashboard widgets : calendrier de la semaine scopé par rôle + activité récente scopée à
  l'école (`2026-07-20-dashboard-widgets.md`)
- Admin Traductions CRUD : `TranslationsController`, pages Index/Create/Edit alignées sur
  `DataTable`/`useTranslation()`, `ActivityObserver` sur `Translation`
  (`2026-06-05-translations-crud.md`)
- Notification admin soumission école : `SchoolPendingNotification` (email) + filtre
  `is_active` sur les admins destinataires (`2026-06-05-school-submission-notification.md`)
- Calendrier Timesheets hebdomadaire + wizard 4 étapes : `WeeklyCalendar` sur `Timesheets/Index`,
  wizard 4 étapes avec pré-check de conflit délégué à `NoTimesheetConflict`, scoping école sur
  `create()`/`store()`/`checkConflict()` (`2026-06-05-timesheets-calendar-wizard.md`)
- Notifications in-app : canal `database` sur `SchoolPendingNotification`, prop Inertia
  `unreadNotifications`, `NotificationsController` (markRead/markAllRead), cloche + dropdown
  dans `AppSidebarHeader.vue` (`2026-08-20-inapp-notifications.md`)
- Responsive mobile (pages quotidiennes) : composable `useMediaQuery`, `PageHeader` empilable,
  grille `WeeklyCalendar` densifiée sous 640px, dropdown notifications plafonné à l'écran
  (`2026-08-20-mobile-responsive.md`)
- Module présence : table `attendances`, roster élèves par séance (`app/Concerns/
  ResolvesAttendanceRoster.php`, source unique partagée affichage/validation), upsert batch
  scopé à la section + à l'école active, carte sur `Timesheets/Show.vue`
  (`2026-08-21-attendance.md`)
- Audit + correction autorisation cross-école : trait `ScopesRouteBindingToActiveSchool`
  appliqué à 9 modèles (lecture, via route-model binding), + scoping des champs FK côté
  écriture sur 5 controllers (Subjects/Schedules/SectionCourses/Lessons/Timesheets — trouvé par
  la revue finale, hors périmètre de l'audit initial qui ne couvrait que la lecture), + route
  `user-school-roles` remise dans le groupe admin, + `SchoolPanelController` vérifie
  l'appartenance, + fix `withTrashed()` sur 4 modèles (régression : un parent soft-deleted
  rendait un enregistrement inaccessible même pour son propriétaire légitime)
  (`2026-08-21-cross-school-scoping.md`)
- 10 pages Vue manquantes qui faisaient planter en 500 les routes `roles.*`,
  `user-school-roles.*` et `section-courses.*` (`admin/web/Roles/*`, `admin/web/
  UserSchoolRoles/{Index,Create}`, `power-user/web/SectionCourses/*`) — pré-existant, découvert
  par la revue finale de l'audit sécurité
- `TimesheetsController::update()` ne persistait pas `schedule_id`/`subject_id`/`classroom_id`/
  `user_school_role_id` (lus seulement pour la vérification de conflit) — les dropdowns du
  formulaire d'édition changeaient visuellement mais rien n'était enregistré. Corrigé avec le
  même scoping école que `store()`, tests de régression dans `TimesheetUpdateTest.php`
- `AttendancesController::store()` passé en `==` (au lieu de `===` strict) pour la comparaison
  `school_id`, par cohérence avec le reste du code
- Tests positifs "accès à sa propre école fonctionne toujours" ajoutés pour les 8 modèles scopés
  qui n'en avaient pas (`Course`/`Section`/`Lesson`/`Resource`/`SectionCourse`/`Subject`/
  `Schedule`/`Timesheet`) — seul `Classroom` en avait un jusqu'ici
- Middleware `EnsureCanManage` (alias `can-manage`) : les routes d'écriture Power User
  (`courses`/`sections`/`section-courses`/`classrooms`/`subjects`/`lessons`/`schedules`/
  `timesheets`/`resources`/`planning.duplicate`/`attendances.store`) sont maintenant réservées
  à Administrateur/Power User/Directeur (aligné sur `MANAGE_ROLES`/`canManage` déjà existants) ;
  Professeur/Élève gardent l'accès lecture (`index`/`show`) conformément à CLAUDE.md
- `routes/api.php` n'avait aucune restriction de rôle sur `api/users`/`api/roles`/`api/schools`/
  `api/classrooms`/`api/subjects`/`api/lessons`/`api/schedules`/`api/timesheets`/
  `api/resources` (juste être connecté suffisait, `guard => ['web']` dans `config/sanctum.php`)
  — n'importe quel utilisateur, même Élève, pouvait faire du CRUD complet sur n'importe quel
  compte ou rôle de la plateforme. Découvert en inspectant `route:list` pendant le fix du
  middleware de rôle Power User. Surface morte en pratique (aucun `createToken`, aucune page Vue
  ne consomme ces endpoints) mais exploitable dès maintenant depuis un navigateur déjà connecté.
  Corrigé en appliquant les mêmes middlewares `admin`/`can-manage` que les routes web
  équivalentes, tests de régression dans `ApiAuthorizationTest.php`

## 🎯 Sprint 24/08 : terminé — toutes les features prévues sont livrées, dette sécurité fermée

## ✅ Complété (backlog "priorité normale")
- Licence Open Source GPL v3 (`LICENSE` + `composer.json`/`package.json`)
- Mobile : flash SSR desktop→mobile sur `WeeklyCalendar` éliminé — dimensions passées en
  custom properties CSS (`--wc-*`) résolues par media query dès la première peinture, au lieu
  d'un calcul JS dépendant de `onMounted`
- Tests PHPUnit de disponibilité : 4 tests ajoutés à `TimesheetConflictTest.php` isolant la
  disponibilité réelle (créneau non-chevauchant, créneaux adjacents à la limite, `ignoreId` sur
  update, timesheet soft-deleted qui libère le créneau) — le fixture `scheduleD` existait déjà
  mais n'était jamais utilisé
- Export CSV RGPD : `UsersController::export()`, réservé admin, couvre le droit d'accès/
  portabilité (art. 15/20)
- Notifications in-app étendues : `TimesheetAssignedNotification` et
  `TimesheetCancelledNotification` (canal database + mail), au-delà de la soumission école
- Module cantine v1 (inscription + présence repas) : optionnel par école
  (`School::cantine_enabled`), `cantine_registrations` (récurrent par jour) +
  `cantine_presences` (occurrence par date), même pattern inscription/occurrence que le module
  présence des cours
- Module notes/bulletins PDF v1 (notes par matière, pas de moyennes/appréciations) :
  `barryvdh/laravel-dompdf`, saisie réservée aux rôles de gestion, consultation scopée par rôle
  (un élève ne voit et ne télécharge que ses propres notes/bulletin)

## ✅ Correction du modèle de permissions (analyse fonctionnelle)
- Les rôles de gestion du contenu académique (cours, horaires, sections, matières, notes,
  cantine) sont **Power User / Secrétariat / Professeur**, pas Administrateur/Directeur —
  corrigé dans `EnsureCanManage`, `GradesController`, `useSchool.ts::canManage`. Résout au
  passage la dette "un professeur ne peut pas consulter les notes de ses élèves" (Professeur
  est maintenant un rôle de gestion pour les notes).
- Boutons Créer/Modifier/Supprimer masqués côté frontend pour les rôles qui n'ont pas le droit
  (`canManage`), sur toutes les pages Index/Show concernées — évite qu'un utilisateur sans le
  droit tombe sur une page 403 en cliquant un bouton qui ne devrait pas s'afficher pour lui
- Bug préexistant trouvé au passage : le lien nav "Utilisateurs" (`/users`, réservé
  Administrateur) était présent dans le menu de Power User/Directeur/Secrétariat — 403 garanti
  au clic. Retiré des 3 menus.

## ✅ Corrections découvertes en testant la démo
- Présences impossibles à prendre à l'avance : `AttendancesController::store()` rejette
  désormais un timesheet dont la date est dans le futur (backend + UI cachée côté
  `Timesheets/Show.vue`)
- Chevauchement de créneaux dans `DemoSchoolSeeder` : anciens créneaux de versions précédentes
  jamais nettoyés (clé d'identité basée sur `name`, qui a changé de format) — identité stable
  (section_course_id + day_of_week + start_time) + nettoyage automatique des créneaux obsolètes
- Perf `DemoSchoolSeeder` : 12.6s → 4.9s (bcrypt recalculé inutilement 20 fois pour le même mot
  de passe partagé) — nécessaire car l'exécution via `railway ssh` semble avoir une limite de
  temps qui coupe la connexion sur les commandes trop longues
- "Dupliquer pour l'année" (`DuplicatePlanningController`) renvoyait du JSON brut à un appel
  `router.post()` d'Inertia côté frontend → navigation plein écran vers le JSON au lieu du
  message inline. Corrigé en redirect + flash (comme partout ailleurs). Ne passait pas non plus
  par `NoTimesheetConflict` : un an de planning dupliqué pouvait double-booker silencieusement
  un prof/salle/section déjà engagés ailleurs à cette date — chaque date cible est maintenant
  validée avant création, les conflits sont ignorés (comptés dans le message) plutôt que de
  bloquer toute la génération
- `Timesheets/Index.vue` : vue par période (Semaine / Mois / Trimestre) en plus de la semaine
  seule — `TimesheetsController::index()` accepte `period` + `date` (ancre), calcule la plage
  correspondante ; le calendrier grille (`WeeklyCalendar`) reste réservé à la vue semaine, mois/
  trimestre utilisent la vue liste

## 🟡 Note pour plus tard (pas encore de plan)
- [ ] Jours fériés/congés automatiques : quand la localité de l'école sera ajoutée (fonctionnalité
  pas encore construite), en déduire les jours fériés du pays (fête nationale, Noël, etc.) pour
  les exclure automatiquement de la planification (génération de feuilles de temps, duplication
  de planning sur l'année). Nécessite d'abord le champ localité/pays sur `School`.

---
*Dernière mise à jour : vue Semaine/Mois/Trimestre sur les feuilles de temps, fix duplication
planning (réponse Inertia + respect des conflits), note ajoutée pour les jours fériés futurs*
