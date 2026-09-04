import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import fs from 'node:fs';
import path from 'node:path';
import laravel from 'laravel-vite-plugin';
import { defineConfig, type Plugin } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

// vite-plugin-pwa writes sw.js directly to disk using its own `outDir`
// option (correctly landing in public/), but it emits manifest.webmanifest
// as a Rollup asset instead — which always follows Vite's *actual* build
// output dir (public/build/, set by laravel-vite-plugin), ignoring the
// plugin's `outDir` option. Relocate it to the true site root after build
// so it's reachable at /manifest.webmanifest instead of /build/manifest.webmanifest.
//
// `npm run build:ssr` runs `vite build && vite build --ssr` — this plugin's
// writeBundle hook fires for both passes, but the SSR pass never emits its
// own manifest.webmanifest (SSR output goes elsewhere), so `from` correctly
// won't exist on that second pass; `to` will already exist from the first
// pass. Only the combination of "neither file exists" means the relocation
// genuinely failed — a future vite-plugin-pwa upgrade could change the
// emitted path/filename and silently break this. Fail the build loudly in
// that case instead of shipping a stale or missing manifest.
function relocatePwaManifest(): Plugin {
    return {
        name: 'relocate-pwa-manifest',
        apply: 'build',
        writeBundle() {
            const from = path.resolve(__dirname, 'public/build/manifest.webmanifest');
            const to = path.resolve(__dirname, 'public/manifest.webmanifest');
            if (fs.existsSync(from)) {
                fs.renameSync(from, to);
            }
            if (!fs.existsSync(to)) {
                throw new Error(
                    `[relocate-pwa-manifest] Expected ${to} to exist after build, but it doesn't. ` +
                        `vite-plugin-pwa likely emitted manifest.webmanifest to a different path than ` +
                        `${from} (check for an outDir/filename/version change) — update relocatePwaManifest() accordingly.`,
                );
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            // Laravel sert public/ à la racine du site, mais Vite (via
            // laravel-vite-plugin) construit dans public/build/ — sans ce
            // outDir explicite, le manifest et le service worker finiraient
            // sous /build/manifest.webmanifest, hors de portée pour contrôler
            // le site entier.
            outDir: 'public',
            manifestFilename: 'manifest.webmanifest',
            filename: 'sw.js',
            // laravel-vite-plugin sets Vite's `base` to `/build/` during
            // build, which vite-plugin-pwa inherits by default for the
            // manifest's `scope`/`start_url` resolution — override to `/`
            // since the PWA is served from the site root, not /build/.
            base: '/',
            registerType: 'autoUpdate',
            injectRegister: false,
            manifest: {
                name: 'School App',
                short_name: 'School App',
                description: 'Gestion scolaire — notes, horaires, absences.',
                lang: 'fr',
                theme_color: '#171717',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/',
                icons: [
                    { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // Inertia sert chaque navigation via une vraie requête serveur
                // (session, CSRF, DB) — pas de mise en cache agressive du HTML/
                // des routes qui pourrait servir une réponse Inertia périmée.
                // Le service worker n'existe ici que pour rendre l'app
                // installable, pas pour un mode hors-ligne.
                globPatterns: [],
                navigateFallback: null,
                // Sans ça, le SW fraîchement enregistré au tout premier chargement
                // n'active/ne contrôle la page qu'au rechargement suivant — et
                // Chrome ne déclenche `beforeinstallprompt` qu'une fois le SW actif
                // ET en contrôle. Résultat observé en prod : le bouton Télécharger
                // tombe sur les instructions manuelles au premier clic, même sur
                // Chrome, jusqu'à un F5. skipWaiting + clientsClaim font prendre le
                // contrôle immédiatement, dès la toute première visite — sans risque
                // ici puisqu'aucun contenu n'est mis en cache (globPatterns: []).
                skipWaiting: true,
                clientsClaim: true,
            },
        }),
        relocatePwaManifest(),
    ],

    server: {
        host: 'localhost',
        port: 3636,
        strictPort: true,
        cors: true,
        origin: 'http://localhost:3636',
    },
});