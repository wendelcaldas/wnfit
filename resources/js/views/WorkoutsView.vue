<template>
    <AppShell
        eyebrow="Treinos"
        title="Biblioteca de treinos"
        description="Crie, organize e reutilize programas de treino para seus alunos."
        search-placeholder="Buscar alunos, treinos, planos..."
    >
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-xl" :class="card.wrap"><component :is="card.icon" class="h-5 w-5" :class="card.color" /></div>
                    <div><p class="text-sm text-[var(--wn-muted)]">{{ card.label }}</p><p class="mt-1 text-2xl font-bold">{{ card.value }}</p></div>
                </div>
            </article>
        </section>

        <section class="panel-card mt-5 p-0">
            <div class="grid gap-4 border-b border-[var(--wn-line)] p-5 lg:grid-cols-[1fr_200px_190px_auto_auto]">
                <label class="input-shell">
                    <Search class="h-5 w-5 text-[var(--wn-muted)]" />
                    <input v-model="filters.q" class="auth-input" placeholder="Buscar por nome ou objetivo..." @input="scheduleLoad" />
                </label>
                <select v-model="filters.objective" class="form-control" @change="loadWorkouts">
                    <option value="todos">Todos os objetivos</option>
                    <option v-for="objective in objectives" :key="objective" :value="objective">{{ objective }}</option>
                </select>
                <select v-model="filters.status" class="form-control" @change="loadWorkouts">
                    <option value="todos">Todos os status</option><option value="ativo">Ativos</option><option value="rascunho">Rascunhos</option><option value="arquivado">Arquivados</option>
                </select>
                <button class="btn-secondary justify-center" type="button" @click="resetFilters"><SlidersHorizontal class="h-5 w-5" />Limpar</button>
                <RouterLink to="/treinos/novo" class="btn-primary justify-center"><Plus class="h-5 w-5" />Novo treino</RouterLink>
            </div>

            <div v-if="loading" class="grid min-h-72 place-items-center p-8 text-sm text-[var(--wn-muted)]">Carregando treinos...</div>
            <div v-else-if="!workouts.length" class="grid min-h-80 place-items-center p-8 text-center">
                <div class="max-w-md">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><Dumbbell class="h-7 w-7" /></div>
                    <h2 class="mt-5 text-xl font-semibold">{{ hasFilters ? 'Nenhum treino encontrado' : 'Monte seu primeiro treino' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--wn-muted)]">{{ hasFilters ? 'Tente remover os filtros para encontrar outros treinos.' : 'Crie modelos organizados por objetivo e nivel. Depois, eles poderao ser associados aos alunos.' }}</p>
                    <RouterLink v-if="!hasFilters" to="/treinos/novo" class="btn-primary mx-auto mt-5"><Plus class="h-5 w-5" />Criar primeiro treino</RouterLink>
                </div>
            </div>
            <div v-else class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                <RouterLink v-for="workout in workouts" :key="workout.id" :to="`/treinos/${workout.id}/editar`" class="rounded-xl border border-[var(--wn-line)] bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-xl bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><Dumbbell class="h-5 w-5" /></div>
                        <span :class="statusClass(workout.status)">{{ statusLabel(workout.status) }}</span>
                    </div>
                    <h2 class="mt-4 text-lg font-semibold">{{ workout.name }}</h2>
                    <p class="mt-1 text-sm text-[var(--wn-primary-strong)]">{{ workout.objective }}</p>
                    <p class="mt-3 line-clamp-2 min-h-10 text-sm leading-5 text-[var(--wn-muted)]">{{ workout.description || 'Sem descricao cadastrada.' }}</p>
                    <div class="mt-4 grid grid-cols-3 gap-2 border-y border-[var(--wn-line)] py-4 text-center text-xs">
                        <div><p class="font-bold text-[var(--wn-ink)]">{{ workout.sessionsPerWeek }}x</p><p class="mt-1 text-[var(--wn-muted)]">semana</p></div>
                        <div><p class="font-bold text-[var(--wn-ink)]">{{ workout.durationWeeks }}</p><p class="mt-1 text-[var(--wn-muted)]">semanas</p></div>
                        <div><p class="font-bold capitalize text-[var(--wn-ink)]">{{ workout.level }}</p><p class="mt-1 text-[var(--wn-muted)]">nivel</p></div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs text-[var(--wn-muted)]"><span>Por {{ workout.author }}</span><span>{{ workout.updatedAt }}</span></div>
                </RouterLink>
            </div>
        </section>
    </AppShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Archive, CircleCheck, Dumbbell, FilePenLine, Plus, Search, SlidersHorizontal } from 'lucide-vue-next';
import { RouterLink } from 'vue-router';
import AppShell from '../components/AppShell.vue';

const loading = ref(true);
const workouts = ref([]);
const objectives = ref([]);
const summary = reactive({ total: 0, active: 0, drafts: 0, archived: 0 });
const filters = reactive({ q: '', objective: 'todos', status: 'todos' });
let debounce;

const summaryCards = computed(() => [
    { label: 'Total de treinos', value: summary.total, icon: Dumbbell, wrap: 'bg-[var(--wn-primary-soft)]', color: 'text-[var(--wn-primary-strong)]' },
    { label: 'Treinos ativos', value: summary.active, icon: CircleCheck, wrap: 'bg-emerald-50', color: 'text-emerald-600' },
    { label: 'Rascunhos', value: summary.drafts, icon: FilePenLine, wrap: 'bg-amber-50', color: 'text-amber-600' },
    { label: 'Arquivados', value: summary.archived, icon: Archive, wrap: 'bg-slate-100', color: 'text-slate-500' },
]);
const hasFilters = computed(() => filters.q || filters.objective !== 'todos' || filters.status !== 'todos');
const statusClass = (status) => ({ ativo: 'badge-success', rascunho: 'badge-warning', arquivado: 'badge-muted' }[status] ?? 'badge-muted');
const statusLabel = (status) => ({ ativo: 'Ativo', rascunho: 'Rascunho', arquivado: 'Arquivado' }[status] ?? status);

const loadWorkouts = async () => {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/workouts', { params: { q: filters.q, objetivo: filters.objective, status: filters.status } });
        workouts.value = data.workouts;
        objectives.value = data.filters.objectives;
        Object.assign(summary, data.summary);
    } finally { loading.value = false; }
};
const scheduleLoad = () => { clearTimeout(debounce); debounce = setTimeout(loadWorkouts, 250); };
const resetFilters = () => { Object.assign(filters, { q: '', objective: 'todos', status: 'todos' }); loadWorkouts(); };
onMounted(loadWorkouts);
</script>
