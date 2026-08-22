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

## 🎯 Sprint 24/08 : terminé — toutes les features prévues sont livrées, dette sécurité fermée

## 🟡 Dette restante (non bloquante, à traiter quand le temps le permet)
- [ ] Aucun middleware de rôle sur les routes Power User (`courses`, `sections`, `timesheets`,
  etc.) — un Professeur/Élève peut écrire dans sa propre école sur ces routes. Volontairement
  hors scope de l'audit cross-école (c'est une frontière de rôle, pas de tenant), mais à garder
  en tête.

## 🟡 Priorité normale (plus tard)
- [ ] Tests PHPUnit (classroom libre, prof dispo, section dispo)
- [ ] Module cantine (optionnel, activable par école)
- [ ] Module évaluations / bulletins PDF
- [ ] Export CSV (RGPD)
- [ ] Open Source GPL v3
- [ ] Notifications in-app : couvrir d'autres événements métier au-delà de la soumission école
  (feuille de temps assignée, conflit d'horaire, etc.) — v1 volontairement minimale
- [ ] Mobile : corriger le flash de mise en page SSR sur `WeeklyCalendar` (grille desktop rendue
  puis snap vers mobile après hydration) — passer par des CSS custom properties plutôt que des
  computed JS si ça remonte comme gênant en usage réel

---
*Dernière mise à jour : audit + correction sécurité cross-école terminé (lecture ET écriture) —
sprint du 24/08 complet*
