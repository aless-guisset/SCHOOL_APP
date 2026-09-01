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

const nav: NavMap = {
    // ── Administrateur de plateforme ──────────────────────────────────────
    Administrateur: [
        {
            section: 'Plateforme',
            items: [
                { label: 'Dashboard', icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Écoles', icon: Building2, route: '/schools', routeName: 'schools.index' },
                { label: 'Demandes en attente', icon: Hourglass, route: '/schools/pending', routeName: 'schools.pending' },
                { label: 'Utilisateurs', icon: Users, route: '/users', routeName: 'users.index' },
                { label: 'Rôles', icon: ShieldCheck, route: '/roles', routeName: 'roles.index' },
            ],
        },
        {
            section: 'Système',
            items: [
                { label: 'Traductions', icon: Languages, route: '/translations', routeName: 'translations.index' },
                { label: 'Logs', icon: ScrollText, route: '/logs', routeName: 'logs.index' },
            ],
        },
    ],

    // ── Power User (Secrétariat étendu) ────────────────────────────────────
    'Power User': [
        {
            section: 'École',
            items: [
                { label: 'Dashboard', icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Sections', icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                { label: 'Cours', icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                { label: 'Matières', icon: Library, route: '/subjects', routeName: 'subjects.index' },
                { label: 'Notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
            ],
        },
        {
            section: 'Planification',
            items: [
                { label: 'Horaires', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Feuilles de temps', icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                { label: 'Salles', icon: DoorOpen, route: '/classrooms', routeName: 'classrooms.index' },
                { label: 'Ressources', icon: FileText, route: '/resources', routeName: 'resources.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
            ],
        },
    ],

    // ── Directeur ──────────────────────────────────────────────────────────
    Directeur: [
        {
            section: 'Gestion école',
            items: [
                { label: 'Dashboard', icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Sections', icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                { label: 'Cours', icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                { label: 'Horaires', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                { label: 'Gestion des accès', icon: KeyRound, route: '/access-requests', routeName: 'access-requests.index' },
                { label: 'Soumettre une école', icon: Building2, route: '/school/create', routeName: 'school.create' },
                { label: 'Liens parent-élève', icon: UserRound, route: '/parent-links', routeName: 'director.parent-links.index' },
            ],
        },
    ],

    // ── Secrétariat ────────────────────────────────────────────────────────
    // Mêmes droits d'écriture que Power User (EnsureCanManage), même nav.
    Secrétariat: [
        {
            section: 'École',
            items: [
                { label: 'Dashboard', icon: LayoutDashboard, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Sections', icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                { label: 'Cours', icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                { label: 'Matières', icon: Library, route: '/subjects', routeName: 'subjects.index' },
                { label: 'Notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
            ],
        },
        {
            section: 'Planification',
            items: [
                { label: 'Horaires', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Feuilles de temps', icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                { label: 'Salles', icon: DoorOpen, route: '/classrooms', routeName: 'classrooms.index' },
                { label: 'Ressources', icon: FileText, route: '/resources', routeName: 'resources.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
            ],
        },
    ],

    // ── Professeur ─────────────────────────────────────────────────────────
    Professeur: [
        {
            section: 'Mes cours',
            items: [
                { label: 'Dashboard', icon: Home, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Mon horaire', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Feuilles de temps', icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
            ],
        },
        {
            section: 'Mes élèves',
            items: [
                { label: 'Mes sections', icon: GraduationCap, route: '/sections', routeName: 'sections.index' },
                { label: 'Notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                { label: 'Ressources', icon: FileText, route: '/resources', routeName: 'resources.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
            ],
        },
    ],

    // ── Élève ──────────────────────────────────────────────────────────────
    Élève: [
        {
            section: 'Mon espace',
            items: [
                { label: 'Accueil', icon: Home, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Mon horaire', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Feuilles de temps', icon: Clock, route: '/timesheets', routeName: 'timesheets.index' },
                { label: 'Mes cours', icon: BookOpen, route: '/courses', routeName: 'courses.index' },
                { label: 'Mes notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
                { label: 'Ressources', icon: FileText, route: '/resources', routeName: 'resources.index' },
                { label: 'Donner l\'accès', icon: UserRound, route: '/my-access', routeName: 'my-access.show' },
            ],
        },
    ],

    // ── Parent/Tuteur ──────────────────────────────────────────────────────
    Parent: [
        {
            section: 'Suivi',
            items: [
                { label: 'Accueil', icon: Home, route: '/dashboard', routeName: 'dashboard' },
                { label: 'Horaire', icon: Calendar, route: '/schedules', routeName: 'schedules.index' },
                { label: 'Notes', icon: NotebookText, route: '/grades', routeName: 'grades.index' },
                // Filtré dans AppSidebar.vue si school.cantine_enabled est faux.
                { label: 'Cantine', icon: Utensils, route: '/cantine', routeName: 'cantine.index' },
            ],
        },
    ],
};

/**
 * Retourne les groupes de navigation selon le rôle de l'utilisateur.
 * Fallback sur Élève si le rôle est inconnu.
 */
export function useSidebarNav(role: string | null | undefined): NavGroup[] {
    if (!role) return nav['Élève'];
    return nav[role] ?? nav['Élève'];
}
