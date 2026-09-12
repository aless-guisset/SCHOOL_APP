import {
    BookOpen,
    Building2,
    Calendar,
    Clock,
    DoorOpen,
    FileText,
    GraduationCap,
    Home,
    Hourglass,
    KeyRound,
    Languages,
    LayoutDashboard,
    Library,
    NotebookText,
    ScrollText,
    Settings,
    ShieldCheck,
    Stethoscope,
    UserRound,
    Users,
    Utensils,
    type LucideIcon,
} from 'lucide-vue-next';

export interface NavItem {
    label: string;
    icon: LucideIcon;
    route: string;
    routeName?: string;
}

export interface NavGroup {
    section: string;
    items: NavItem[];
}

type NavMap = Record<string, NavGroup[]>;
type Translate = (key: string) => string;

// Construit la nav à chaque appel (pas au chargement du module) : les
// labels dépendent de t(), qui lit les traductions réactives injectées par
// Inertia — un objet figé au chargement du module ne réagirait jamais à un
// changement de langue.
function buildNav(t: Translate): NavMap {
    return {
        // ── Administrateur de plateforme ──────────────────────────────
        Administrateur: [
            {
                section: t('nav.section.platform'),
                items: [
                    { label: t('nav.dashboard'), icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.schools'), icon: Building2, route: '/schools', routeName: 'schools.index' },
                    { label: t('nav.schools_pending'), icon: Hourglass, route: '/schools/pending', routeName: 'schools.pending' },
                    { label: t('nav.users'), icon: Users, route: '/users', routeName: 'users.index' },
                    { label: t('nav.roles'), icon: ShieldCheck, route: '/roles', routeName: 'roles.index' },
                ],
            },
            {
                section: t('nav.section.system'),
                items: [
                    { label: t('nav.translations'), icon: Languages, route: '/translations', routeName: 'translations.index' },
                    { label: t('nav.logs'), icon: ScrollText, route: '/logs', routeName: 'logs.index' },
                ],
            },
        ],

        // ── Power User (Secrétariat étendu) ────────────────────────────
        'Power User': [
            {
                section: t('nav.section.school'),
                items: [
                    { label: t('nav.dashboard'), icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.sections'), icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                    { label: t('nav.courses'), icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                    { label: t('nav.subjects'), icon: Library, route: '/subjects', routeName: 'subjects.index' },
                    { label: t('nav.grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.medical_certificates'), icon: Stethoscope, route: '/medical-certificates', routeName: 'medical-certificates.index' },
                ],
            },
            {
                section: t('nav.section.planning'),
                items: [
                    { label: t('nav.schedules'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.timesheets'), icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                    { label: t('nav.classrooms'), icon: DoorOpen, route: '/classrooms', routeName: 'classrooms.index' },
                    { label: t('nav.resources'), icon: FileText, route: '/resources', routeName: 'resources.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                    { label: t('nav.cantine_wallets'), icon: Utensils, route: '/cantine/wallet', routeName: 'cantine.wallet.index' },
                ],
            },
        ],

        // ── Directeur ────────────────────────────────────────────────
        Directeur: [
            {
                section: t('nav.section.school_management'),
                items: [
                    { label: t('nav.dashboard'), icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.sections'), icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                    { label: t('nav.courses'), icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                    { label: t('nav.schedules'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.medical_certificates'), icon: Stethoscope, route: '/medical-certificates', routeName: 'medical-certificates.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                    { label: t('nav.cantine_wallets'), icon: Utensils, route: '/cantine/wallet', routeName: 'cantine.wallet.index' },
                    { label: t('nav.access_requests'), icon: KeyRound, route: '/access-requests', routeName: 'access-requests.index' },
                    { label: t('nav.school_submit'), icon: Building2, route: '/school/create', routeName: 'school.create' },
                    { label: t('nav.parent_links'), icon: UserRound, route: '/parent-links', routeName: 'director.parent-links.index' },
                ],
            },
        ],

        // ── Secrétariat ──────────────────────────────────────────────
        // Mêmes droits d'écriture que Power User (EnsureCanManage), même nav.
        Secrétariat: [
            {
                section: t('nav.section.school'),
                items: [
                    { label: t('nav.dashboard'), icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.sections'), icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                    { label: t('nav.courses'), icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                    { label: t('nav.subjects'), icon: Library, route: '/subjects', routeName: 'subjects.index' },
                    { label: t('nav.grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.medical_certificates'), icon: Stethoscope, route: '/medical-certificates', routeName: 'medical-certificates.index' },
                ],
            },
            {
                section: t('nav.section.planning'),
                items: [
                    { label: t('nav.schedules'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.timesheets'), icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                    { label: t('nav.classrooms'), icon: DoorOpen, route: '/classrooms', routeName: 'classrooms.index' },
                    { label: t('nav.resources'), icon: FileText, route: '/resources', routeName: 'resources.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                    { label: t('nav.cantine_wallets'), icon: Utensils, route: '/cantine/wallet', routeName: 'cantine.wallet.index' },
                ],
            },
        ],

        // ── Professeur ───────────────────────────────────────────────
        Professeur: [
            {
                section: t('nav.my_courses'),
                items: [
                    { label: t('nav.dashboard'), icon: Home, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.my_schedule'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.my_courses'), icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                ],
            },
            {
                section: t('nav.section.my_students'),
                items: [
                    { label: t('nav.my_sections'), icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                    { label: t('nav.grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.resources'), icon: FileText, route: '/resources', routeName: 'resources.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                ],
            },
        ],

        // ── Élève ────────────────────────────────────────────────────
        Élève: [
            {
                section: t('nav.section.my_space'),
                items: [
                    { label: t('nav.home'), icon: Home, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.my_schedule'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.timesheets'), icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                    { label: t('nav.my_courses'), icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                    { label: t('nav.my_grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.medical_certificates'), icon: Stethoscope, route: '/medical-certificates', routeName: 'medical-certificates.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                    { label: t('nav.resources'), icon: FileText, route: '/resources', routeName: 'resources.index' },
                    { label: t('nav.give_access'), icon: UserRound, route: '/my-access', routeName: 'my-access.show' },
                ],
            },
        ],

        // ── Parent/Tuteur ────────────────────────────────────────────
        Parent: [
            {
                section: t('nav.section.tracking'),
                items: [
                    { label: t('nav.home'), icon: Home, route: '/dashboard', routeName: 'dashboard' },
                    { label: t('nav.schedule'), icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                    { label: t('nav.grades'), icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                    { label: t('nav.medical_certificates'), icon: Stethoscope, route: '/medical-certificates', routeName: 'medical-certificates.index' },
                    // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                    { label: t('nav.cantine'), icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                ],
            },
        ],
    };
}

/**
 * Retourne les groupes de navigation selon le rôle de l'utilisateur.
 * Fallback sur Élève si le rôle est inconnu.
 */
export function useSidebarNav(role: string | null | undefined, t: Translate): NavGroup[] {
    const nav = buildNav(t);
    if (!role) return nav['Élève'];
    return nav[role] ?? nav['Élève'];
}
