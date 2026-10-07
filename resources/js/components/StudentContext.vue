<template>
    <aside class="student-context">
        <section class="panel-card student-identity">
            <div class="student-identity-heading">
                <label class="student-avatar student-photo-control" :class="{ uploading: photoBusy }" :title="photoBusy ? 'Enviando foto' : student.photoUrl && !photoFailed ? 'Trocar foto do aluno' : 'Adicionar foto do aluno'">
                    <img v-if="student.photoUrl && !photoFailed" :src="student.photoUrl" :alt="'Foto de ' + student.name" @error="photoFailed = true" />
                    <span v-else>{{ student.initials }}</span>
                    <span class="student-photo-hint" aria-hidden="true"><Pencil v-if="student.photoUrl && !photoFailed" /><Plus v-else /></span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="photoBusy" aria-label="Adicionar ou trocar foto do aluno" @change="uploadPhoto" />
                </label>
                <div class="student-identity-info">
                    <h2>{{ student.name }}</h2>
                    <div class="student-identity-tags"><span class="badge-success">{{ student.status }}</span><span v-if="student.plan" class="student-plan-tag">{{ student.plan }}</span></div>
                    <p class="student-identity-meta"><strong>Nascimento:</strong> {{ student.birthDate || 'Não informado' }}<template v-if="student.age !== null && student.age !== undefined"> ({{ student.age }} anos)</template></p>
                    <p v-if="student.teacher" class="student-identity-meta">Professor: {{ student.teacher }}</p>
                    <p v-if="student.goal" class="student-identity-meta"><strong>Objetivo:</strong> {{ student.goal }}</p>
                </div>
            </div>
            <nav class="student-context-menu" aria-label="Seções do aluno">
                <button v-for="tab in tabs" :key="tab.key" :class="{ selected: activeTab === tab.key }" :aria-pressed="activeTab === tab.key" @click="$emit('select', tab.key)">{{ tab.label }}</button>
            </nav>
        </section>
        <details class="panel-card student-context-widget" :open="wide">
            <summary>Últimos 7 dias <span class="text-xs text-[var(--wn-muted)]">{{ trainedDays }} dias com treino</span></summary>
            <div class="student-recent-week">
                <div v-for="day in student.recentActivity" :key="day.date" :title="`${dateLabel(day.date)}: ${day.completedSessions ? 'Treinou' : 'Sem registro'}`">
                    <span>{{ weekday(day.date) }}</span>
                    <span class="student-day" :class="{ trained: day.completedSessions > 0 }" :aria-label="`${dateLabel(day.date)}: ${day.completedSessions ? 'Treinou' : 'Sem registro'}`">{{ day.completedSessions ? '✓' : '–' }}</span>
                    <small>{{ Number(day.date.slice(-2)) }}</small>
                </div>
            </div>
            <p class="student-widget-caption">Verde: treino concluído · Cinza: sem registro</p>
        </details>

    </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Pencil, Plus } from 'lucide-vue-next';
const props = defineProps({ student: Object, activeTab: String, tabs: Array });
const emit = defineEmits(['select', 'photo-updated']);
const photoBusy = ref(false);
const photoFailed = ref(false);
watch(() => props.student.photoUrl, () => { photoFailed.value = false; });
watch(() => props.student.id, () => { photoFailed.value = false; });
const uploadPhoto = async event => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { window.alert('Escolha uma foto JPG, PNG ou WebP de até 5 MB.'); return; }
    const studentId = props.student.id;
    const form = new FormData(); form.append('photo', file);
    photoBusy.value = true;
    try {
        const { data } = await window.axios.post(`/api/students/${studentId}/photo`, form);
        emit('photo-updated', { studentId, photoUrl: data.photoUrl });
    } catch (error) {
        if (props.student.id === studentId) window.alert(error.response?.data?.errors?.photo?.[0] || 'Não foi possível enviar a foto. Tente novamente.');
    } finally { photoBusy.value = false; }
};
const wide = ref(false);
let media;
const updateWidth = () => { wide.value = media.matches; };
onMounted(() => { media = window.matchMedia('(min-width: 1100px)'); updateWidth(); media.addEventListener('change', updateWidth); });
onUnmounted(() => media?.removeEventListener('change', updateWidth));
const weekday = date => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
const dateLabel = date => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR');
const trainedDays = computed(() => props.student.recentActivity?.filter(day => day.completedSessions > 0).length ?? 0);
</script>
