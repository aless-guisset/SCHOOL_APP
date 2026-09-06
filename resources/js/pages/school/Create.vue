<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Building2, CheckCircle2, ChevronLeft, ChevronRight, Info, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const page = usePage<{ flash?: { type: string; message: string } }>();

const STEPS = ['Infos', 'Modules', 'Inviter des gens'];
const step = ref(1);

// Doit rester synchronisé avec SchoolOnboardingController::CREATION_INVITABLE_ROLES.
const INVITABLE_ROLES: { reference: string; label: string }[] = [
    { reference: 'PROF', label: 'Professeur' },
    { reference: 'SEC', label: 'Secrétariat' },
    { reference: 'POWER', label: 'Power User' },
    { reference: 'DIR', label: 'Directeur' },
];

const form = useForm({
    name: '',
    email: '',
    phone_number: '',
    address: '',
    description: '',
    cantine_enabled: false,
    invites: [] as { email: string; role_reference: string }[],
});

// ── Étape 3 : ajout d'une ligne d'invitation ────────────────────────────────
const newInviteEmail = ref('');
const newInviteRole = ref('');

function addInvite() {
    if (!newInviteEmail.value.trim() || !newInviteRole.value) return;
    form.invites.push({ email: newInviteEmail.value.trim(), role_reference: newInviteRole.value });
    newInviteEmail.value = '';
    newInviteRole.value = '';
}

function removeInvite(index: number) {
    form.invites.splice(index, 1);
}

function roleLabel(reference: string): string {
    return INVITABLE_ROLES.find(r => r.reference === reference)?.label ?? reference;
}

const inviteErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'invites' || key.startsWith('invites.'))
        .map(([, message]) => message)
);

// ── Navigation étapes ────────────────────────────────────────────────────────
const canGoNext = computed(() => {
    if (step.value === 1) return !!form.name.trim();
    return true;
});

function next() {
    form.clearErrors();
    step.value++;
}
function back() {
    step.value--;
}

// Renvoie à l'étape où se trouve le premier champ en erreur, pour qu'un rejet
// serveur (ex: cantine_meal_price manquant) ne laisse pas l'utilisateur bloqué
// sur l'étape 3 sans rien voir.
function fieldStep(field: string): number {
    if (field.startsWith('invites.')) return 3;
    if (field === 'cantine_enabled') return 2;
    return 1;
}

function submit() {
    form.post('/school/create', {
        onError: (errors) => {
            const fields = Object.keys(errors);
            if (fields.length) {
                step.value = Math.min(...fields.map(fieldStep));
            }
        },
    });
}
</script>

<template>
    <Head title="Créer un établissement" />

    <AuthLayout>
        <div class="mx-auto w-full max-w-lg space-y-6 px-4 py-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10">
                    <Building2 class="size-7 text-primary" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Créer un établissement</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Renseignez les informations de votre école.
                </p>
            </div>

            <!-- Message succès -->
            <div
                v-if="page.props.flash?.type === 'success'"
                class="flex items-start gap-3 rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-sm text-green-600 dark:text-green-400"
            >
                <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                <p>{{ page.props.flash.message }}</p>
            </div>

            <!-- Info box -->
            <div class="flex items-start gap-3 rounded-xl border border-blue-500/20 bg-blue-500/10 p-4 text-sm text-blue-600 dark:text-blue-400">
                <Info class="mt-0.5 size-5 shrink-0" />
                <p>
                    Votre demande sera examinée par un administrateur de la plateforme.
                    Vous serez notifié(e) dès qu'elle sera traitée.
                </p>
            </div>

            <!-- Indicateur étapes -->
            <div class="flex items-center gap-2">
                <template v-for="(label, i) in STEPS" :key="i">
                    <div class="flex items-center gap-1.5">
                        <div
                            class="flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                            :class="step > i + 1
                                ? 'bg-primary text-primary-foreground'
                                : step === i + 1
                                    ? 'border-2 border-primary text-primary'
                                    : 'border border-muted-foreground/30 text-muted-foreground'"
                        >
                            <CheckCircle2 v-if="step > i + 1" class="size-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="hidden text-xs sm:block"
                            :class="step === i + 1 ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                        >{{ label }}</span>
                    </div>
                    <div v-if="i < STEPS.length - 1" class="h-px flex-1 bg-border" />
                </template>
            </div>

            <Card>
                <CardHeader v-if="step === 1">
                    <CardTitle class="text-base">Informations de l'établissement</CardTitle>
                    <CardDescription>Renseignez les informations de base de votre école.</CardDescription>
                </CardHeader>
                <CardContent :class="{ 'pt-6': step !== 1 }">

                    <!-- Étape 1 : Infos -->
                    <div v-if="step === 1" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="name">Nom de l'établissement <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="ex : Institut Saint-Joseph"
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="email">Email de contact</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="contact@ecole.be"
                                :class="{ 'border-destructive': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="phone">Téléphone</Label>
                            <Input
                                id="phone"
                                v-model="form.phone_number"
                                type="tel"
                                placeholder="+32 2 123 45 67"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="address">Adresse</Label>
                            <Input
                                id="address"
                                v-model="form.address"
                                placeholder="Rue de l'École 12, 1000 Bruxelles"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="description">Description (optionnel)</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Quelques mots sur votre établissement..."
                                rows="3"
                            />
                        </div>
                    </div>

                    <!-- Étape 2 : Modules -->
                    <div v-else-if="step === 2" class="space-y-4">
                        <h2 class="font-semibold">Modules</h2>
                        <div class="flex items-center gap-2">
                            <Checkbox id="cantine_enabled" v-model="form.cantine_enabled" />
                            <Label for="cantine_enabled">Activer la cantine</Label>
                        </div>
                        <p v-if="form.cantine_enabled" class="text-xs text-muted-foreground">
                            Les prix des repas se règlent dans l'application une fois l'établissement approuvé.
                        </p>
                    </div>

                    <!-- Étape 3 : Inviter des gens -->
                    <div v-else class="space-y-4">
                        <h2 class="font-semibold">Inviter des gens</h2>
                        <p class="text-xs text-muted-foreground">
                            Facultatif — ces invitations ne seront envoyées qu'une fois votre
                            établissement approuvé.
                        </p>

                        <div v-if="form.invites.length" class="space-y-2">
                            <div
                                v-for="(invite, i) in form.invites"
                                :key="i"
                                class="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                            >
                                <div>
                                    <p class="font-medium">{{ invite.email }}</p>
                                    <p class="text-xs text-muted-foreground">{{ roleLabel(invite.role_reference) }}</p>
                                </div>
                                <Button type="button" variant="ghost" size="icon" @click="removeInvite(i)">
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-2 rounded-md border border-dashed border-border p-3">
                            <div class="space-y-1.5">
                                <Label for="invite_email">Email</Label>
                                <Input id="invite_email" v-model="newInviteEmail" type="email" placeholder="email@example.com" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>Rôle</Label>
                                <Select v-model="newInviteRole">
                                    <SelectTrigger><SelectValue placeholder="Choisir un rôle" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="r in INVITABLE_ROLES" :key="r.reference" :value="r.reference">
                                            {{ r.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="button" variant="outline" size="sm" :disabled="!newInviteEmail.trim() || !newInviteRole" @click="addInvite">
                                Ajouter
                            </Button>
                        </div>
                        <div v-if="inviteErrors.length" class="space-y-1">
                            <p v-for="(msg, idx) in inviteErrors" :key="idx" class="text-xs text-destructive">{{ msg }}</p>
                        </div>
                    </div>

                    <!-- Navigation boutons -->
                    <div class="mt-6 flex gap-3">
                        <Button v-if="step > 1" type="button" variant="outline" @click="back">
                            <ChevronLeft class="size-4" />Retour
                        </Button>
                        <div class="flex-1" />
                        <Button
                            v-if="step < 3"
                            type="button"
                            :disabled="!canGoNext"
                            @click="next"
                        >
                            Suivant<ChevronRight class="size-4" />
                        </Button>
                        <Button
                            v-else
                            type="button"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            <span v-if="form.processing">Envoi en cours...</span>
                            <span v-else>Créer l'établissement</span>
                        </Button>
                    </div>

                </CardContent>
            </Card>

            <!-- Lien retour login -->
            <p class="text-center text-xs text-muted-foreground">
                <a href="/school/select" class="font-medium text-primary underline-offset-2 hover:underline">
                    ← Retour à la sélection
                </a>
            </p>
        </div>
    </AuthLayout>
</template>
