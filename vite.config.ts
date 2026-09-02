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
            includeAssets: ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'],
            manifest: {
                name: 'School App',
                short_name: 'School App',
                description: 'Gestion scolaire — notes, horaires, absences.',
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