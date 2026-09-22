<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import FlashMessage from '@/components/FlashMessage.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useSchool } from '@/composables/useSchool';
import { useTranslation } from '@/composables/useTranslation';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTranslation();
const { canManageStructure: canManage } = useSchool();

const DAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

type Devoir = { id: number; title: string; description: string | null; due_date: string };
type CourseResourceItem = { id: number; title: string; type: 'file' | 'link'; url: string | null; attachment_original_name: string | null };
type SessionHistoryRow = { id: number; date: string; start_time: string | null; end_time: string | null; attendance_submitted: boolean };

const props = defineProps<{
    sectionCourse: {
        id: number;
        name: string;
        description: string | null;
        total_hours: number;
        hours_per_session: number;
        is_active: boolean;
        course: { id: number; name: string } | null;
        section_user: { section: { id: number; name: string } | null } | null;
        schedules: Array<{ id: number; day_of_week: number; start_time: string; end_time: string }>;
    };
    hoursPlanned: number;
    hoursConsumed: number;
    hoursRemaining: number;
    completion: number;
    can_manage_course: boolean;
    devoirs: Devoir[];
    courseResources: CourseResourceItem[];
    sessionHistory: SessionHistoryRow[];
    todayTimesheetId: number | null;
}>();

const breadcrumbs = [
    { label: t('nav.my_subjects'), href: '/section-courses' },
    { label: props.sectionCourse.name },
];

function destroy() {
    if (confirm('Supprimer cette association ?')) router.delete(`/section-courses/${props.sectionCourse.id}`);
}

// ── Devoirs ───────────────────────────────────────────────────────────────
const showDevoirForm = ref(false);
const editingDevoirId = ref<number | null>(null);
const devoirForm = useForm({ title: '', description: '', due_date: '' });

function startCreateDevoir() {
    editingDevoirId.value = null;
    devoirForm.reset();
    showDevoirForm.value = true;
}

function startEditDevoir(d: Devoir) {
    editingDevoirId.value = d.id;
    devoirForm.title = d.title;
    devoirForm.description = d.description ?? '';
    devoirForm.due_date = d.due_date;
    showDevoirForm.value = true;
}

function cancelDevoirForm() {
    showDevoirForm.value = false;
    editingDevoirId.value = null;
    devoirForm.reset();
}

function submitDevoir() {
    const onSuccess = () => {
        devoirForm.reset();
        showDevoirForm.value = false;
        editingDevoirId.value = null;
    };

    if (editingDevoirId.value) {
        devoirForm.put(`/devoirs/${editingDevoirId.value}`, { onSuccess });
    } else {
        devoirForm.post(`/section-courses/${props.sectionCourse.id}/devoirs`, { onSuccess });
    }
}

function destroyDevoir(id: number) {
    if (confirm(t('mon_cours.confirm_delete_devoir'))) router.delete(`/devoirs/${id}`);
}

// ── Ressources ────────────────────────────────────────────────────────────
const showResourceForm = ref(false);
const resourceForm = useForm({
    title: '',
    type: 'link' as 'file' | 'link',
    url: '',
    attachment: null as File | null,
});

function onResourceFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    resourceForm.attachment = target.files?.[0] ?? null;
}

function submitResource() {
    resourceForm.post(`/section-courses/${props.sectionCourse.id}/resources`, {
        forceFormData: true,
        onSuccess: () => {
            resourceForm.reset();
            showResourceForm.value = false;
        },
    });
}

function destroyResource(id: number) {
    if (confirm(t('mon_cours.confirm_delete_resource'))) router.delete(`/course-resources/${id}`);
}
</script>

<template>
    <Head :title="sectionCourse.name" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-2xl">
            <FlashMessage />
            <PageHeader :title="sectionCourse.name" :breadcrumbs="breadcrumbs">
                <template v-if="canManage" #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/section-courses/${sectionCourse.id}/edit`"><Edit class="size-4" />{{ t('action.edit') }}</Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="size-4" />{{ t('action.delete') }}
                    </Button>
                </template>
            </PageHeader>

            <Card v-if="todayTimesheetId" class="mb-4 border-primary/40 bg-primary/5">
                <CardContent class="flex items-center justify-between pt-6">
                    <span class="text-sm">{{ t('mon_cours.attendance_pending_today') }}</span>
                    <Button size="sm" as-child>
                        <Link :href="`/timesheets/${todayTimesheetId}`">{{ t('mon_cours.take_attendance') }}</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">{{ t('label.informations') }}</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.status') }}</dt>
                            <dd><Badge :variant="sectionCourse.is_active ? 'default' : 'secondary'">{{ sectionCourse.is_active ? t('label.active') : t('label.inactive') }}</Badge></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.course') }}</dt>
                            <dd>{{ sectionCourse.course?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('label.section') }}</dt>
                            <dd>{{ sectionCourse.section_user?.section?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('mon_cours.hours_summary') }}</dt>
                            <dd>{{ hoursPlanned }}h / {{ hoursConsumed }}h / {{ hoursRemaining }}h ({{ completion }}%)</dd>
                        </div>
                        <div v-if="sectionCourse.description" class="flex flex-col gap-1">
                            <dt class="text-muted-foreground">{{ t('label.description') }}</dt>
                            <dd class="rounded bg-muted p-2 text-xs">{{ sectionCourse.description }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="sectionCourse.schedules?.length" class="mt-4">
                <CardHeader><CardTitle class="text-base">{{ t('mon_cours.slots_count', { count: String(sectionCourse.schedules.length) }) }}</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1">
                        <li v-for="s in sectionCourse.schedules" :key="s.id" class="flex items-center justify-between text-sm">
                            <span>{{ DAYS[s.day_of_week] }}</span>
                            <span class="text-muted-foreground">{{ s.start_time }} — {{ s.end_time }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Devoirs -->
            <Card class="mt-4">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">{{ t('mon_cours.devoirs') }}</CardTitle>
                    <Button v-if="can_manage_course" size="sm" variant="outline" @click="showDevoirForm ? cancelDevoirForm() : startCreateDevoir()">
                        {{ showDevoirForm ? t('action.cancel') : t('mon_cours.add_devoir') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <form v-if="showDevoirForm" class="mb-4 space-y-3 rounded-md border border-border p-3" @submit.prevent="submitDevoir">
                        <div class="space-y-1.5">
                            <Label for="devoir-title">{{ t('label.title') }} *</Label>
                            <Input id="devoir-title" v-model="devoirForm.title" :class="{ 'border-destructive': devoirForm.errors.title }" />
                            <p v-if="devoirForm.errors.title" class="text-xs text-destructive">{{ devoirForm.errors.title }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="devoir-description">{{ t('label.description') }}</Label>
                            <Textarea id="devoir-description" v-model="devoirForm.description" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="devoir-due-date">{{ t('mon_cours.due_date') }} *</Label>
                            <Input id="devoir-due-date" v-model="devoirForm.due_date" type="date" :class="{ 'border-destructive': devoirForm.errors.due_date }" />
                            <p v-if="devoirForm.errors.due_date" class="text-xs text-destructive">{{ devoirForm.errors.due_date }}</p>
                        </div>
                        <Button type="submit" size="sm" :disabled="devoirForm.processing">{{ t('action.save') }}</Button>
                    </form>

                    <p v-if="!devoirs.length" class="text-sm text-muted-foreground">{{ t('mon_cours.no_devoirs') }}</p>
                    <ul v-else class="space-y-2">
                        <li v-for="d in devoirs" :key="d.id" class="flex items-start justify-between rounded-md border border-border p-2 text-sm">
                            <div>
                                <p class="font-medium">{{ d.title }}</p>
                                <p v-if="d.description" class="text-xs text-muted-foreground">{{ d.description }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ t('mon_cours.due_date') }} : {{ d.due_date }}</p>
                            </div>
                            <div v-if="can_manage_course" class="flex items-center gap-1">
                                <Button variant="ghost" size="sm" @click="startEditDevoir(d)">
                                    <Edit class="size-4" />
                                </Button>
                                <Button variant="ghost" size="sm" @click="destroyDevoir(d.id)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Ressources -->
            <Card class="mt-4">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">{{ t('mon_cours.resources') }}</CardTitle>
                    <Button v-if="can_manage_course" size="sm" variant="outline" @click="showResourceForm = !showResourceForm">
                        {{ showResourceForm ? t('action.cancel') : t('mon_cours.add_resource') }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <form v-if="showResourceForm" class="mb-4 space-y-3 rounded-md border border-border p-3" @submit.prevent="submitResource">
                        <div class="space-y-1.5">
                            <Label for="resource-title">{{ t('label.title') }} *</Label>
                            <Input id="resource-title" v-model="resourceForm.title" :class="{ 'border-destructive': resourceForm.errors.title }" />
                            <p v-if="resourceForm.errors.title" class="text-xs text-destructive">{{ resourceForm.errors.title }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>{{ t('mon_cours.resource_type') }}</Label>
                            <Select v-model="resourceForm.type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="link">{{ t('mon_cours.resource_type_link') }}</SelectItem>
                                    <SelectItem value="file">{{ t('mon_cours.resource_type_file') }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="resourceForm.type === 'link'" class="space-y-1.5">
                            <Label for="resource-url">URL *</Label>
                            <Input id="resource-url" v-model="resourceForm.url" type="url" placeholder="https://…" :class="{ 'border-destructive': resourceForm.errors.url }" />
                            <p v-if="resourceForm.errors.url" class="text-xs text-destructive">{{ resourceForm.errors.url }}</p>
                        </div>
                        <div v-else class="space-y-1.5">
                            <Label for="resource-attachment">{{ t('mon_cours.resource_file_hint') }}</Label>
                            <input
                                id="resource-attachment"
                                type="file"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
                                class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm"
                                @change="onResourceFileChange"
                            />
                            <p v-if="resourceForm.errors.attachment" class="text-xs text-destructive">{{ resourceForm.errors.attachment }}</p>
                        </div>
                        <Button type="submit" size="sm" :disabled="resourceForm.processing">{{ t('action.save') }}</Button>
                    </form>

                    <p v-if="!courseResources.length" class="text-sm text-muted-foreground">{{ t('mon_cours.no_resources') }}</p>
                    <ul v-else class="space-y-2">
                        <li v-for="r in courseResources" :key="r.id" class="flex items-center justify-between rounded-md border border-border p-2 text-sm">
                            <a v-if="r.type === 'link'" :href="r.url ?? '#'" target="_blank" rel="noopener" class="text-primary hover:underline">{{ r.title }}</a>
                            <a v-else :href="`/course-resources/${r.id}/attachment`" class="text-primary hover:underline">{{ r.title }} ({{ r.attachment_original_name }})</a>
                            <Button v-if="can_manage_course" variant="ghost" size="sm" @click="destroyResource(r.id)">
                                <Trash2 class="size-4" />
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Historique des séances -->
            <Card class="mt-4">
                <CardHeader><CardTitle class="text-base">{{ t('mon_cours.session_history') }}</CardTitle></CardHeader>
                <CardContent>
                    <p v-if="!sessionHistory.length" class="text-sm text-muted-foreground">{{ t('mon_cours.no_sessions') }}</p>
                    <ul v-else class="space-y-1">
                        <li v-for="s in sessionHistory" :key="s.id">
                            <Link :href="`/timesheets/${s.id}`" class="flex items-center justify-between rounded-md border border-border p-2 text-sm hover:bg-muted">
                                <span>{{ s.date }} — {{ s.start_time }} → {{ s.end_time }}</span>
                                <Badge :variant="s.attendance_submitted ? 'default' : 'secondary'">
                                    {{ s.attendance_submitted ? t('mon_cours.attendance_submitted') : t('mon_cours.attendance_pending') }}
                                </Badge>
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
