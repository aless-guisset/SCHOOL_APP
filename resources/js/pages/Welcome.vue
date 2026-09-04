<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Moon, Sun } from 'lucide-vue-next';
import { onMounted, onUnmounted } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { login, register } from '@/routes';
import { edit } from '@/routes/profile';
import { create as createSchool } from '@/routes/school';

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

withDefaults(
    defineProps<{
        canRegister: boolean;
        auth?: { user?: { roles?: string[] } };
        currentRole?: string | null;
    }>(),
    {
        canRegister: true,
        auth: undefined,
        currentRole: null,
    },
);

const modules = [
    {
        name: 'Fiches de présence',
        desc: 'Enregistrez les présences par session et suivez les heures réalisées par rapport au plafond défini.',
    },
    {
        name: 'Horaires',
        desc: "Créez des créneaux hebdomadaires et gérez automatiquement les plafonds d'heures par cours.",
    },
    {
        name: 'Cours & matières',
        desc: 'Organisez vos cours et matières par section, niveau et enseignant assigné.',
    },
    {
        name: 'Sections & élèves',
        desc: 'Regroupez les élèves par section et attribuez des rôles précis à chaque intervenant.',
    },
    {
        name: 'Salles de classe',
        desc: 'Gérez vos espaces, leur localisation et leur attribution aux sessions planifiées.',
    },
    {
        name: 'Rôles & accès',
        desc: 'Contrôlez les accès par école avec des rôles distincts : admin, enseignant, élève.',
    },
];

const steps = [
    { title: 'Créez votre compte', desc: 'Inscrivez-vous gratuitement avec votre adresse e-mail.' },
    { title: 'Soumettez votre école', desc: 'Remplissez le formulaire. Notre équipe valide votre demande.' },
    { title: 'Configurez vos modules', desc: 'Ajoutez sections, cours, horaires et assignez les rôles.' },
    { title: 'Gérez au quotidien', desc: 'Suivez les présences et horaires en temps réel.' },
];

let observers: IntersectionObserver[] = [];

onMounted(() => {
    const groups: { sel: string; stagger: number }[] = [
        { sel: '[data-sr="left"]', stagger: 90 },
        { sel: '[data-sr="right"]', stagger: 90 },
        { sel: '[data-sr="up"]', stagger: 110 },
    ];

    groups.forEach(({ sel, stagger }) => {
        const els = Array.from(document.querySelectorAll<HTMLElement>(sel));
        const obs = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    const el = e.target as HTMLElement;
                    const i = parseInt(el.dataset.idx ?? '0');
                    if (e.isIntersecting) {
                        setTimeout(() => el.classList.add('in'), i * stagger);
                    } else {
                        el.classList.remove('in');
                    }
                });
            },
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
        );
        els.forEach((el, i) => {
            el.dataset.idx = String(i);
            obs.observe(el);
        });
        observers.push(obs);
    });
});

onUnmounted(() => {
    observers.forEach((o) => o.disconnect());
    observers = [];
});
</script>

<template>
    <Head title="SchoolApp — Gestion scolaire simplifiée" />

    <div class="page">
        <!-- NAV -->
        <header class="nav">
            <span class="nav-logo">school<b>app</b></span>
            <div class="nav-right">
                <button class="icon-btn off" disabled title="Télécharger l'application — pas disponible pour le moment">
                    <Download :size="16" />
                </button>
                <button class="icon-btn" @click="toggleTheme" :title="resolvedAppearance === 'dark' ? 'Passer en thème clair' : 'Passer en thème sombre'">
                    <Moon v-if="resolvedAppearance === 'dark'" :size="16" />
                    <Sun v-else :size="16" />
                </button>
                <template v-if="auth?.user">
                    <Link v-if="currentRole !== 'Élève'" :href="createSchool()" class="btn btn-sm btn-ghost">Soumettre une école</Link>
                    <button
                        v-if="auth?.user?.roles?.includes('super_admin')"
                        class="btn btn-sm off"
                        disabled
                    >
                        Admin panel
                    </button>
                    <Link :href="edit()" class="avatar" title="Profil">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6" />
                        </svg>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="login()" class="btn btn-sm btn-ghost">Se connecter</Link>
                    <Link v-if="canRegister" :href="register()" class="btn btn-sm btn-green">S'inscrire</Link>
                </template>
            </div>
        </header>

        <!-- HERO -->
        <section class="hero">
            <p class="badge">Plateforme de gestion scolaire</p>
            <h1>Gérez votre école<br /><span class="green">simplement.</span></h1>
            <p class="hero-sub">
                SchoolApp centralise les présences, horaires, cours et salles de classe en une seule
                plateforme accessible à toutes les écoles.
            </p>
            <div class="hero-cta">
                <Link v-if="canRegister" :href="register()" class="btn btn-green btn-lg">
                    Commencer gratuitement
                </Link>
                <a href="#modules" class="btn btn-lg">Voir les modules</a>
            </div>
        </section>

        <hr class="sep" />

        <!-- MODULES -->
        <section id="modules" class="section">
            <div class="section-head" data-sr="up">
                <p class="eyebrow">Modules</p>
                <h2>Tout ce dont votre école a besoin</h2>
                <p class="section-sub">Des modules pensés pour couvrir chaque aspect de la gestion scolaire.</p>
            </div>
            <div class="grid">
                <div
                    v-for="(mod, i) in modules"
                    :key="mod.name"
                    class="card"
                    :data-sr="i % 2 === 0 ? 'left' : 'right'"
                >
                    <p class="card-num">0{{ i + 1 }}</p>
                    <h3 class="card-title">{{ mod.name }}</h3>
                    <p class="card-desc">{{ mod.desc }}</p>
                </div>
            </div>
        </section>

        <hr class="sep" />

        <!-- ÉTAPES -->
        <section class="section section-narrow">
            <div class="section-head" data-sr="up">
                <p class="eyebrow">Comment ça marche</p>
                <h2>Démarrez en 4 étapes</h2>
            </div>
            <div class="steps">
                <div
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="step"
                    :data-sr="i % 2 === 0 ? 'left' : 'right'"
                >
                    <span class="step-num">{{ i + 1 }}</span>
                    <div>
                        <p class="step-title">{{ step.title }}</p>
                        <p class="step-desc">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA FINAL -->
        <div class="cta-wrap">
            <div class="cta-block" data-sr="up">
                <h2>Prêt à moderniser votre école ?</h2>
                <p>Rejoignez les écoles qui font confiance à SchoolApp.</p>
                <Link v-if="canRegister" :href="register()" class="btn btn-white btn-lg">
                    Créer un compte gratuit
                </Link>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <span class="nav-logo">school<b>app</b></span>
            <span>© 2025 SchoolApp</span>
        </footer>
    </div>
</template>

<style scoped>
/* Palette claire par défaut, sur des custom properties pour permettre un
   thème sombre (.dark, cf useAppearance.ts) sans dupliquer chaque règle. */
.page {
    --bg: #ffffff;
    --fg: #111111;
    --muted: #666666;
    --muted-2: #999999;
    --border: #e6e6e1;
    --hover-bg: #f5f5f3;
    --green: #1a3d2b;
    --green-hover: #2d6a4a;
    --green-bg: #e8f5ee;
    --green-border: #b7dfc8;

    background: var(--bg);
    color: var(--fg);
    min-height: 100vh;
    font-family: ui-sans-serif, system-ui, sans-serif;
    color-scheme: light;
}

.dark .page {
    --bg: #0a0a0a;
    --fg: #f2f2f2;
    --muted: #a3a3a3;
    --muted-2: #737373;
    --border: #262626;
    --hover-bg: #1a1a1a;
    --green: #5eba8c;
    --green-hover: #7fd1ab;
    --green-bg: #123024;
    --green-border: #2f5943;

    color-scheme: dark;
}

/* NAV */
.nav {
    position: sticky;
    top: 0;
    z-index: 50;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    height: 54px;
    padding: 0 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.nav-logo { font-size: 17px; color: var(--green); }
.nav-logo b { color: var(--fg); font-weight: 700; }
.nav-right { display: flex; align-items: center; gap: 8px; }

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--fg);
    text-decoration: none;
    transition: background 0.15s;
    white-space: nowrap;
    line-height: 1;
}
.btn:hover { background: var(--hover-bg); }
.btn-sm { padding: 6px 14px; font-size: 13px; }
.btn-lg { padding: 11px 26px; font-size: 15px; border-radius: 10px; }
.btn-ghost { border-color: transparent; color: var(--muted); }
.btn-ghost:hover { background: var(--hover-bg); }
.btn-green { background: var(--green); color: #ffffff; border-color: var(--green); }
.btn-green:hover { background: var(--green-hover); }
.btn-white { background: #ffffff; color: #1a3d2b; border-color: #ffffff; }
.btn-white:hover { background: #f0f0ee; }
.off { opacity: 0.35; cursor: not-allowed; pointer-events: none; }

.avatar {
    width: 34px;
    height: 34px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--green-bg);
    border: 1px solid var(--green-border);
    color: var(--green);
    cursor: pointer;
}
.avatar svg { width: 16px; height: 16px; }

.icon-btn {
    width: 34px;
    height: 34px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.icon-btn:hover { background: var(--hover-bg); color: var(--fg); }

/* HERO */
.hero {
    padding: 88px 1.75rem 72px;
    text-align: center;
    max-width: 640px;
    margin: 0 auto;
}
.badge {
    display: inline-block;
    background: var(--green-bg);
    color: var(--green);
    border: 1px solid var(--green-border);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 24px;
}
.hero h1 {
    font-size: clamp(34px, 5.5vw, 50px);
    font-weight: 700;
    line-height: 1.12;
    letter-spacing: -1px;
    color: var(--fg);
    margin-bottom: 20px;
}
.green { color: var(--green); }
.hero-sub {
    font-size: 16px;
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 32px;
}
.hero-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

/* SEPARATEUR */
.sep {
    border: none;
    border-top: 1px solid var(--border);
    margin: 0 1.75rem;
}

/* SECTIONS */
.section {
    padding: 72px 1.75rem;
    max-width: 860px;
    margin: 0 auto;
}
.section-narrow { max-width: 640px; }
.section-head { margin-bottom: 40px; }
.eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--green);
    margin-bottom: 10px;
}
.section h2 {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.4px;
    color: var(--fg);
    margin-bottom: 10px;
}
.section-sub { font-size: 15px; color: var(--muted); line-height: 1.65; }

/* MODULES */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 14px;
}
.card {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px 22px;
    background: var(--bg);
    transition: border-color 0.2s, opacity 0.5s ease, transform 0.5s ease;
}
.card:hover { border-color: var(--green-border); }
.card-num {
    font-size: 11px;
    font-weight: 600;
    color: var(--green);
    letter-spacing: 1px;
    margin-bottom: 10px;
}
.card-title { font-size: 14px; font-weight: 600; color: var(--fg); margin-bottom: 8px; }
.card-desc { font-size: 13px; color: var(--muted); line-height: 1.6; }

/* ÉTAPES */
.steps { display: flex; flex-direction: column; }
.step {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    transition: opacity 0.5s ease, transform 0.5s ease;
}
.step:last-child { border-bottom: none; }
.step-num {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--green);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.step-title { font-size: 14px; font-weight: 600; color: var(--fg); margin-bottom: 4px; }
.step-desc { font-size: 13px; color: var(--muted); line-height: 1.6; }

/* CTA */
.cta-wrap { padding: 0 1.75rem 72px; }
.cta-block {
    background: #1a3d2b;
    border-radius: 14px;
    padding: 48px 36px;
    text-align: center;
    transition: opacity 0.55s ease, transform 0.55s ease;
}
.cta-block h2 { font-size: 26px; font-weight: 700; color: #ffffff; margin-bottom: 10px; }
.cta-block p { font-size: 15px; color: #a8d5bb; margin-bottom: 26px; line-height: 1.6; }

/* FOOTER */
.footer {
    border-top: 1px solid var(--border);
    padding: 22px 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--muted-2);
    background: var(--bg);
}

/* SCROLL REVEAL */
[data-sr='left']  { opacity: 0; transform: translateX(-36px); }
[data-sr='right'] { opacity: 0; transform: translateX(36px); }
[data-sr='up']    { opacity: 0; transform: translateY(28px); }

[data-sr='left'].in,
[data-sr='right'].in,
[data-sr='up'].in {
    opacity: 1;
    transform: translate(0);
}
</style>