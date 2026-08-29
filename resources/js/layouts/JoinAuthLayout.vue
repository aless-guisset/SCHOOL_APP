<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
    badge?: string;
}>();
</script>

<template>
    <div class="join-page">
        <header class="join-nav">
            <Link href="/" class="join-logo">school<b>app</b></Link>
        </header>

        <div class="join-wrap">
            <div class="join-card">
                <p v-if="badge" class="join-badge">{{ badge }}</p>
                <h1 v-if="title" class="join-title">{{ title }}</h1>
                <p v-if="description" class="join-desc">{{ description }}</p>
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Même palette que la page d'accueil (resources/js/pages/Welcome.vue) —
   #1a3d2b (vert foncé) surchargé sur --primary/--ring fait automatiquement
   reteinter les composants shadcn (Button, focus ring) sans y toucher :
   les custom properties CSS traversent les frontières "scoped" de Vue.

   Ces pages doivent rester en clair même si l'utilisateur a le thème sombre
   actif (classe .dark sur <html>, cf useAppearance.ts) : sans réécrire aussi
   --foreground/--muted-foreground/--input/--border ici, les inputs/labels
   shadcn héritaient des valeurs sombres (texte quasi blanc, placeholders peu
   contrastés) sur ce fond clair, les rendant illisibles. */
.join-page {
    --background: hsl(0 0% 100%);
    --foreground: hsl(0 0% 3.9%);
    --card: hsl(0 0% 100%);
    --card-foreground: hsl(0 0% 3.9%);
    --primary: hsl(149 40% 17%);
    --primary-foreground: hsl(0 0% 100%);
    --secondary: hsl(0 0% 92.1%);
    --secondary-foreground: hsl(0 0% 9%);
    --muted: hsl(0 0% 96.1%);
    --muted-foreground: hsl(0 0% 45.1%);
    --accent: hsl(0 0% 96.1%);
    --accent-foreground: hsl(0 0% 9%);
    --destructive: hsl(0 84.2% 60.2%);
    --destructive-foreground: hsl(0 0% 98%);
    --border: hsl(0 0% 92.8%);
    --input: hsl(0 0% 89.8%);
    --ring: hsl(149 40% 17%);
    background: #fafaf8;
    /* `color` est hérité en CSS : le redéfinir ici (pas seulement la
       custom property --foreground) est nécessaire car `body` a déjà
       calculé sa propre couleur de texte plus haut dans l'arbre (via
       text-foreground) — un composant comme Label, qui n'applique pas
       lui-même de classe text-foreground, hérite sinon cette valeur déjà
       résolue (potentiellement sombre) au lieu de la relire ici. */
    color: hsl(0 0% 3.9%);
    min-height: 100vh;
    color-scheme: light;
    font-family: ui-sans-serif, system-ui, sans-serif;
}

.join-nav {
    height: 54px;
    display: flex;
    align-items: center;
    padding: 0 1.75rem;
    border-bottom: 1px solid #e8e8e4;
    background: #ffffff;
}
.join-logo {
    font-size: 17px;
    color: #1a3d2b;
    text-decoration: none;
}
.join-logo b {
    color: #111111;
    font-weight: 700;
}

.join-wrap {
    display: flex;
    justify-content: center;
    padding: 56px 1.5rem;
}
.join-card {
    width: 100%;
    max-width: 440px;
    background: #ffffff;
    border: 1px solid #e8e8e4;
    border-radius: 14px;
    padding: 36px 32px;
}

.join-badge {
    display: inline-block;
    background: #e8f5ee;
    color: #1a3d2b;
    border: 1px solid #b7dfc8;
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 16px;
}
.join-title {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.4px;
    color: #111111;
    margin-bottom: 8px;
}
.join-desc {
    font-size: 14px;
    color: #666666;
    line-height: 1.6;
    margin-bottom: 24px;
}
</style>
