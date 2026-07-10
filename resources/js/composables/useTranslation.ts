import { usePage } from '@inertiajs/vue3';

type Translations = Record<string, string>;

/**
 * Composable de traduction — lit les traductions injectées par Inertia (depuis la DB).
 *
 * Usage :
 *   const { t, locale } = useTranslation()
 *   t('dashboard.title')              // → 'Tableau de bord'
 *   t('greeting', { name: 'Alex' })   // → 'Bonjour Alex' (si la valeur contient :name)
 */
export function useTranslation() {
    const page = usePage<{
        translations: Translations;
        locale: string;
    }>();

    /**
     * Traduit une clé.
     * Si la clé n'existe pas en DB, retourne la clé elle-même (fail-safe).
     */
    function t(key: string, replace: Record<string, string> = {}): string {
        const translations = page.props.translations ?? {};
        let value = translations[key] ?? key;

        for (const [placeholder, replacement] of Object.entries(replace)) {
            value = value.replaceAll(`:${placeholder}`, replacement);
        }

        return value;
    }

    const locale = page.props.locale ?? 'fr';

    return { t, locale };
}
