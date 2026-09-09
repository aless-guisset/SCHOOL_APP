<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type LangMeta = { flag: string; name: string; title: string; subtitle: string; confirm: string };

const LANGS: Record<string, LangMeta> = {
    fr: { flag: '🇫🇷', name: 'Français',  title: "Choisir la langue de l'application", subtitle: 'Survolez une langue pour la découvrir, cliquez pour la sélectionner', confirm: 'Confirmer' },
    en: { flag: '🇬🇧', name: 'English',   title: 'Choose the application language',    subtitle: 'Hover a language to preview, click to select', confirm: 'Confirm' },
    nl: { flag: '🇳🇱', name: 'Nederlands', title: 'Kies de taal van de applicatie',      subtitle: 'Beweeg over een taal om te bekijken, klik om te selecteren', confirm: 'Bevestigen' },
    es: { flag: '🇪🇸', name: 'Español',    title: 'Elegir el idioma de la aplicación',   subtitle: 'Pase el cursor sobre un idioma, haga clic para seleccionar', confirm: 'Confirmar' },
};

const page = usePage<{ showLanguagePicker: boolean; availableLocales: string[] }>();

const show = computed(() => page.props.showLanguagePicker);
const available = computed(() => page.props.availableLocales);

const selected = ref('fr');
const hovered = ref<string | null>(null);
const submitting = ref(false);

const displayed = computed(() => hovered.value ?? selected.value);
const meta = computed(() => LANGS[displayed.value] ?? LANGS.fr);

onMounted(() => {
    const browserLang = (navigator.language || 'fr').slice(0, 2);
    selected.value = available.value.includes(browserLang) ? browserLang : 'fr';
});

function choose(code: string) {
    selected.value = code;
}

function confirm() {
    submitting.value = true;
    router.post('/locale', { locale: selected.value }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="language-picker-title"
            class="flex w-full max-w-md flex-col items-center gap-6 rounded-md border border-border bg-card p-8 text-card-foreground shadow-lg"
        >
            <div class="text-center">
                <h2 id="language-picker-title" class="text-xl font-semibold">{{ meta.title }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ meta.subtitle }}</p>
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <button
                    v-for="code in available" :key="code"
                    type="button"
                    class="flex h-24 w-24 flex-col items-center justify-center gap-1.5 rounded-md border transition-transform hover:-translate-y-1"
                    :class="selected === code ? 'border-2 border-primary bg-primary/10' : 'border-border bg-card'"
                    @mouseenter="hovered = code"
                    @mouseleave="hovered = null"
                    @click="choose(code)"
                >
                    <span class="text-2xl">{{ LANGS[code]?.flag }}</span>
                    <span class="text-xs font-semibold">{{ LANGS[code]?.name }}</span>
                </button>
            </div>

            <button
                type="button"
                class="rounded-md bg-primary px-7 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                :disabled="submitting"
                @click="confirm"
            >
                {{ meta.confirm }}
            </button>
        </div>
    </div>
</template>
