# School App — CLAUDE.md

## Stack
- **Backend** : Laravel 13, PHP 8.4, Fortify (auth), Inertia.js
- **Frontend** : Vue 3 (Composition API, `<script setup>`), TypeScript, Tailwind CSS, shadcn-vue (composants UI dans `resources/js/components/ui/`)
- **DB** : MySQL (migrations dans `database/migrations/`)

## Architecture clé

### Rôles utilisateurs
- `Administrateur` : gestion plateforme (écoles, users, rôles, traductions, logs)
- `Power User` : secrétariat (horaires, sections, cours, timesheets)
- `Professeur` / `Étudiant` : lecture

### Modèles principaux
| Modèle | Table | Notes |
|--------|-------|-------|
| School | schools | status: P=pending, A=approved, R=rejected |
| UserSchoolRole | users_schools_roles | lien user ↔ école ↔ rôle |
| Schedule | schedules | créneau récurrent (day_of_week + start/end_time) |
| Timesheet | timesheets | occurrence réelle (date + prof + salle + matière) |
| SectionCourse | sections_courses | lien section ↔ cours, pivot pour Schedule |
| Translation | translations | tag_key + language_code + translated_value |
| ActivityLog | activity_logs | observer sur 12 modèles |

### Patterns Backend
- Controllers Inertia → `Inertia::render('path/Page', [...props])`
- Routes groupées par middleware : `admin`, `school.context`
- Validation dans le controller (`$request->validate()`), Custom Rules dans `app/Rules/`
- Observers dans `app/Observers/`, enregistrés dans `AppServiceProvider`
- `TranslationService::getForLocale()` → cache 1h, injecté via `HandleInertiaRequests`

### Patterns Frontend
- Pages dans `resources/js/pages/{role}/web/{Entity}/{Create|Edit|Index|Show}.vue`
- Composants partagés : `PageHeader`, `DataTable`, `FlashMessage`, composants `ui/`
- `useTranslation()` composable → `t('clé')` depuis les props Inertia partagées
- `useSidebarNav(role)` → navigation dynamique par rôle
- Flash messages : `session('flash', ['type' => 'success', 'message' => '...'])`
- `pendingCount` prop partagée → badge sidebar "Demandes en attente"

### Shared Inertia Props (HandleInertiaRequests)
`auth.user`, `school`, `currentRole`, `userSchools`, `sidebarOpen`, `translations`, `locale`, `pendingCount`, `flash`, `routeName`

## Commandes utiles
```bash
php artisan migrate          # Appliquer migrations
php artisan cache:clear      # Vider cache (translations incluses)
php artisan route:list       # Voir toutes les routes
npm run dev                  # Dev server Vite
npm run build                # Build production
```

## Conventions de code
- Noms de routes : `{entity}.{action}` (ex: `schools.pending`, `translations.index`)
- Pages admin : `admin/web/{Entity}/`
- Pages power-user : `power-user/web/{Entity}/`
- Soft deletes sur tous les modèles métier
- `created_by` / `updated_by` systématique
- TypeScript strict dans les pages Vue (defineProps avec types explicites)
