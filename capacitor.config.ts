import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.schoolapp.app',
    appName: 'School App',
    webDir: 'public/build',
    server: {
        // La WebView charge directement la prod — Inertia fait une vraie
        // requête serveur à chaque navigation (session, CSRF, DB), pas de
        // bundle statique embarqué possible.
        url: 'https://school-app-p.up.railway.app',
        cleartext: false,
    },
};

export default config;
