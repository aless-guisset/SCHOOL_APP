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

## 🎯 Sprint 24/08 : terminé — toutes les features prévues sont livrées

## 🔴 Dette sécurité découverte (à traiter en priorité, pas encore de plan écrit)
- [ ] Audit autorisation cross-école sur `TimesheetsController` : `show`/`edit`/`update`/`destroy`
  utilisent le route-model binding implicite SANS vérifier l'école active — un power-user peut
  lire/modifier/supprimer une feuille de temps d'une autre école par ID, ET (depuis le module
  présence) lire le roster nominatif des élèves d'une autre école via la prop `roster` de
  `show()`. Le côté écriture (`attendances.store`) est déjà scopé (`8b72099`) ; c'est
  spécifiquement le côté lecture de `TimesheetsController` qui reste ouvert, avec un blast radius
  plus large qu'avant (noms d'élèves, pas juste horaires). `edit()` expose aussi tous les
  créneaux de toutes les écoles (même défaut que `create()` avant correction). Probable que
  d'autres controllers aient le même pattern — vérifier plus largement, pas juste Timesheets.
- [ ] `AttendancesController::store()` compare `session('active_school_id')` en `===` strict —
  seul endroit du code à faire ça (les ~30 autres passent par un `where()` SQL, comparaison
  lâche). Sûr avec la config actuelle (mariadb, pas d'émulation PDO) mais latent — passer en
  `==` par cohérence/robustesse.

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
*Dernière mise à jour : module présence terminé — sprint du 24/08 complet, priorité passe à
l'audit sécurité cross-école sur TimesheetsController*
