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

## 🎯 Deadline lundi 24/08 — priorité au nombre de features livrées
- [ ] Calendrier Timesheets hebdomadaire + wizard 4 étapes (`2026-06-05-timesheets-calendar-wizard.md`) — en cours
- [ ] Module communication / notifications in-app — pas encore de plan écrit
- [ ] Layouts mobile (pages/[role]/mobile/) — pas encore de plan écrit
- [ ] Module présence (absences, certificats) — pas encore de plan écrit

## 🟡 Priorité normale (plus tard)
- [ ] Tests PHPUnit (classroom libre, prof dispo, section dispo)
- [ ] Module cantine (optionnel, activable par école)
- [ ] Module évaluations / bulletins PDF
- [ ] Export CSV (RGPD)
- [ ] Open Source GPL v3

---
*Dernière mise à jour : school-submission-notification terminé, cap sur timesheets-wizard puis communication/mobile/présence pour le 24/08*
