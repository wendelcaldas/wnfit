<template>
    <AppShell visual-refresh eyebrow="Treinos" title="Treinos" description="Organize seus modelos. Entregue o próximo treino com clareza.">
        <template #page-actions><RouterLink to="/treinos/novo" class="btn-primary justify-center"><Plus :size="18" />Novo treino</RouterLink></template>

        <section class="workout-metrics" aria-label="Indicadores da biblioteca">
            <button v-for="card in summaryCards" :key="card.label" class="stat-card workout-metric" :class="{ selected: filters.status === card.status }" :aria-pressed="filters.status === card.status" :title="card.caption" @click="filterStatus(card.status)">
                <component :is="card.icon" :size="18" aria-hidden="true" :class="card.tone" /><span>{{ card.label }}</span><strong>{{ loading ? '…' : loadError ? '—' : card.value }}</strong>
            </button>
        </section>

        <section class="panel-card workout-library !p-0" aria-labelledby="library-title">
            <div class="workout-library-heading"><div><h2 id="library-title">Biblioteca de modelos</h2><p>Escolha um treino para editar ou entregar a um aluno.</p></div><span v-if="!loading && !loadError" class="workout-result-count">{{ workouts.length }} {{ workouts.length === 1 ? 'modelo' : 'modelos' }}</span></div>
            <form class="workout-filters" @submit.prevent="loadWorkouts">
                <label class="workout-search input-shell"><Search :size="18" aria-hidden="true" /><input v-model="filters.q" type="search" class="auth-input" aria-label="Buscar treino por nome ou objetivo" placeholder="Buscar nome ou objetivo" @input="scheduleLoad" /></label>
                <label class="workout-filter"><span>Objetivo</span><select v-model="filters.objective" class="form-control" @change="loadWorkouts"><option value="todos">Todos os objetivos</option><option v-for="objective in objectives" :key="objective" :value="objective">{{ objective }}</option></select></label>
                <label class="workout-filter"><span>Situação</span><select v-model="filters.status" class="form-control" @change="loadWorkouts"><option value="todos">Todos os modelos</option><option value="ativo">Ativos</option><option value="rascunho">Rascunhos</option><option value="arquivado">Arquivados</option></select></label>
                <button class="btn-secondary justify-center workout-clear" type="button" :disabled="!hasFilters" @click="resetFilters"><RotateCcw :size="15" />Limpar</button>
            </form>

            <div v-if="loading" class="workout-empty" role="status"><Dumbbell :size="26" aria-hidden="true" /><p>Carregando biblioteca…</p></div>
            <div v-else-if="loadError" class="workout-empty"><CircleAlert :size="26" aria-hidden="true" /><h3>Não foi possível carregar a biblioteca</h3><button class="btn-secondary" @click="loadWorkouts">Tentar novamente</button></div>
            <div v-else-if="!workouts.length" class="workout-empty"><span class="workout-empty-icon"><Dumbbell :size="26" aria-hidden="true" /></span><h3>{{ hasFilters ? 'Nenhum modelo neste filtro' : 'Seu próximo treino começa aqui' }}</h3><p>{{ hasFilters ? 'Ajuste a busca ou limpe os filtros para ver outros modelos.' : 'Crie um modelo e reutilize sua estrutura nas fichas dos alunos.' }}</p><button v-if="hasFilters" class="btn-secondary" @click="resetFilters">Limpar filtros</button><RouterLink v-else to="/treinos/novo" class="btn-primary"><Plus :size="17" />Criar primeiro treino</RouterLink></div>
            <div v-else class="workout-list">
                <article v-for="workout in workouts" :key="workout.id" class="workout-row">
                    <span class="workout-row-icon" aria-hidden="true"><Dumbbell :size="20" /></span>
                    <div class="workout-row-copy"><div class="workout-row-title"><RouterLink :to="`/treinos/${workout.id}/editar`"><h3>{{ workout.name }}</h3></RouterLink><span :class="statusClass(workout.status)">{{ statusLabel(workout.status) }}</span></div><p class="workout-row-subtitle">{{ workout.objective }}<span v-if="workout.level"> · {{ workout.level }}</span></p><div class="workout-row-meta"><span><CalendarDays :size="13" aria-hidden="true" />{{ workout.sessionsPerWeek }}x por semana</span><span><Clock3 :size="13" aria-hidden="true" />{{ workout.durationWeeks }} semanas</span><span class="workout-updated">Atualizado em {{ workout.updatedAt }}</span></div></div>
                    <div class="workout-row-actions"><button v-if="workout.status === 'ativo'" class="btn-primary workout-deliver" :disabled="busy" @click="openAssignment(workout)"><UserRoundPlus :size="16" />Entregar ficha</button><RouterLink v-else :to="`/treinos/${workout.id}/editar`" class="btn-secondary">{{ workout.status === 'rascunho' ? 'Continuar edição' : 'Ver modelo' }}<ArrowUpRight :size="15" /></RouterLink><details class="workout-more" @keydown.esc="closeMenu($event)"><summary class="workout-more-button" :aria-label="'Mais ações para ' + workout.name"><Ellipsis :size="20" /></summary><div class="workout-menu"><RouterLink :to="`/treinos/${workout.id}/editar`"><Pencil :size="15" />Editar modelo</RouterLink><button :disabled="busy" @click="duplicate(workout, $event)"><Copy :size="15" />Duplicar modelo</button><button :disabled="busy" @click="changeStatus(workout, $event)"><Archive :size="15" />{{ workout.status === 'arquivado' ? 'Restaurar rascunho' : 'Arquivar modelo' }}</button></div></details></div>
                </article>
            </div>
        </section>
        <ActionFeedback :message="error" type="error" />
        <div v-if="assignmentWorkout" class="student-modal-backdrop" @click.self="closeAssignment" @keydown.esc.stop="closeAssignment"><section ref="assignmentDialog" class="student-card workout-assignment" role="dialog" aria-modal="true" aria-labelledby="assign-title" @keydown.tab="trapFocus"><div class="workout-assignment-heading"><div><p class="workout-dialog-eyebrow">ENTREGAR FICHA</p><h2 id="assign-title">{{ assignmentWorkout.name }}</h2></div><button ref="assignmentClose" class="icon-button" type="button" aria-label="Fechar" :disabled="busy" @click="closeAssignment"><X :size="19" /></button></div><p class="workout-assignment-description">{{ assignmentWorkout.sessionsPerWeek }}x por semana · {{ assignmentWorkout.durationWeeks }} semanas</p><label class="workout-filter mt-5"><span>Buscar aluno</span><input v-model="studentSearch" type="search" class="form-control" placeholder="Digite o nome do aluno" @input="scheduleStudentSearch" /></label><label class="workout-filter mt-4"><span>Aluno</span><select v-model="assignmentStudent" class="form-control" :disabled="studentsLoading"><option value="">{{ studentsLoading ? 'Buscando alunos…' : 'Selecione um aluno' }}</option><option v-for="student in assignmentStudents" :key="student.id" :value="student.id">{{ student.name }}</option></select></label><p v-if="!studentsLoading && !assignmentStudents.length" class="mt-2 text-sm text-[var(--wn-muted)]">Nenhum aluno encontrado. Tente outro nome.</p><p class="workout-assignment-note">A nova ficha substitui a vigente. As versões anteriores e o histórico de sessões ficam preservados.</p><div class="workout-assignment-footer"><button class="btn-secondary" :disabled="busy" @click="closeAssignment">Cancelar</button><button class="btn-primary" :disabled="busy || studentsLoading || !assignmentStudent" @click="assign">{{ busy ? 'Publicando…' : 'Publicar para o aluno' }}</button></div></section></div>
    </AppShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Archive, ArrowUpRight, CalendarDays, CircleAlert, CircleCheck, Clock3, Copy, Dumbbell, Ellipsis, FilePenLine, Pencil, Plus, RotateCcw, Search, UserRoundPlus, X } from 'lucide-vue-next';
import { RouterLink, useRouter } from 'vue-router';
import AppShell from '../components/AppShell.vue';
import { notify } from '../composables/useNotifications';
const router = useRouter();
const loading = ref(true), loadError = ref(false), error = ref(''), busy = ref(false);
const workouts = ref([]), objectives = ref([]);
const summary = reactive({ total: 0, active: 0, drafts: 0, archived: 0 });
const filters = reactive({ q: '', objective: 'todos', status: 'todos' });
const assignmentWorkout = ref(null), assignmentStudent = ref(''), assignmentStudents = ref([]), studentSearch = ref(''), studentsLoading = ref(false);
const assignmentDialog = ref(null), assignmentClose = ref(null);
let debounce, studentDebounce, requestId = 0, studentRequestId = 0, previousFocus;
const summaryCards = computed(() => [
    { label: 'Todos', value: summary.total, status: 'todos', icon: Dumbbell, tone: '', caption: 'Todos os modelos de treino' },
    { label: 'Ativos', value: summary.active, status: 'ativo', icon: CircleCheck, tone: 'workout-tone-active', caption: 'Modelos prontos para entregar aos alunos' },
    { label: 'Rascunhos', value: summary.drafts, status: 'rascunho', icon: FilePenLine, tone: 'workout-tone-draft', caption: 'Modelos em preparação' },
    { label: 'Arquivados', value: summary.archived, status: 'arquivado', icon: Archive, tone: 'workout-tone-archived', caption: 'Modelos guardados, com fichas anteriores preservadas' },
]);
const hasFilters = computed(() => Boolean(filters.q || filters.objective !== 'todos' || filters.status !== 'todos'));
const statusClass = status => ({ ativo: 'badge-success', rascunho: 'badge-warning', arquivado: 'badge-muted' }[status] ?? 'badge-muted');
const statusLabel = status => ({ ativo: 'Ativo', rascunho: 'Rascunho', arquivado: 'Arquivado' }[status] ?? status);
function closeMenu(event) { const menu = event.currentTarget.closest('details'); menu?.removeAttribute('open'); menu?.querySelector('summary')?.focus(); }
async function loadWorkouts() {
    clearTimeout(debounce); const id = ++requestId; loading.value = true; loadError.value = false; error.value = '';
    try { const { data } = await window.axios.get('/api/workouts', { params: { q: filters.q, objetivo: filters.objective, status: filters.status } }); if (id !== requestId) return; workouts.value = data.workouts; objectives.value = data.filters.objectives; Object.assign(summary, data.summary); }
    catch { if (id === requestId) { loadError.value = true; error.value = 'Não foi possível carregar a biblioteca. Tente novamente.'; } }
    finally { if (id === requestId) loading.value = false; }
}
function scheduleLoad() { clearTimeout(debounce); debounce = setTimeout(loadWorkouts, 250); }
function filterStatus(status) { filters.status = status; loadWorkouts(); }
function resetFilters() { Object.assign(filters, { q: '', objective: 'todos', status: 'todos' }); loadWorkouts(); }
async function duplicate(workout, event) { closeMenu(event); busy.value = true; error.value = ''; try { const { data } = await window.axios.post(`/api/workouts/${workout.id}/duplicate`); notify('Modelo duplicado. Personalize a nova versão.'); await router.push(`/treinos/${data.workout.id}/editar`); } catch { error.value = 'Não foi possível duplicar o treino.'; } finally { busy.value = false; } }
async function changeStatus(workout, event) { closeMenu(event); if (workout.status !== 'arquivado' && !window.confirm('Arquivar este modelo? As fichas já entregues aos alunos serão preservadas.')) return; busy.value = true; error.value = ''; try { await window.axios.patch(`/api/workouts/${workout.id}/status`, { status: workout.status === 'arquivado' ? 'rascunho' : 'arquivado' }); notify(workout.status === 'arquivado' ? 'Modelo restaurado como rascunho.' : 'Modelo arquivado.'); await loadWorkouts(); } catch { error.value = 'Não foi possível alterar a situação do modelo.'; } finally { busy.value = false; } }
async function searchStudents() { const id = ++studentRequestId; studentsLoading.value = true; try { const { data } = await window.axios.get('/api/students', { params: { q: studentSearch.value.trim() } }); if (id === studentRequestId) assignmentStudents.value = data.students; } catch { if (id === studentRequestId) error.value = 'Não foi possível carregar os alunos.'; } finally { if (id === studentRequestId) studentsLoading.value = false; } }
function scheduleStudentSearch() { clearTimeout(studentDebounce); assignmentStudent.value = ''; studentDebounce = setTimeout(searchStudents, 250); }
async function openAssignment(workout) { previousFocus = document.activeElement; error.value = ''; studentSearch.value = ''; assignmentStudent.value = ''; assignmentStudents.value = []; assignmentWorkout.value = workout; await nextTick(); assignmentClose.value?.focus(); searchStudents(); }
async function closeAssignment() { if (busy.value) return; clearTimeout(studentDebounce); studentRequestId++; studentsLoading.value = false; assignmentWorkout.value = null; await nextTick(); previousFocus?.focus(); }
function trapFocus(event) { const elements = [...assignmentDialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)')]; const first = elements[0], last = elements.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
async function assign() { if (busy.value || !assignmentStudent.value) return; busy.value = true; error.value = ''; try { await window.axios.post(`/api/students/${assignmentStudent.value}/workouts/${assignmentWorkout.value.id}`); notify('Ficha publicada para o aluno.'); await router.push(`/alunos/${assignmentStudent.value}?tab=workouts`); } catch (e) { error.value = e.response?.data?.message ?? 'Não foi possível publicar a ficha.'; } finally { busy.value = false; } }
onMounted(loadWorkouts);
onBeforeUnmount(() => { clearTimeout(debounce); clearTimeout(studentDebounce); requestId++; studentRequestId++; });
</script>

<style scoped>
.workout-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.workout-metric { display: flex; align-items: center; gap: 12px; min-width: 0; min-height: 72px; padding: 16px; border-radius: 16px; text-align: left; cursor: pointer; }
.workout-metric > svg { color: var(--wn-primary-strong); flex-shrink: 0; }
.workout-metric > span { flex: 1; font-size: 12px; color: var(--wn-muted); }
.workout-metric > strong { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
.workout-metric:hover, .workout-metric.selected { border-color: #c0d4a8; background: #f5f9ef; }
.workout-metric > .workout-tone-active { color: #38764a; }.workout-metric > .workout-tone-draft { color: #a57820; }.workout-metric > .workout-tone-archived { color: #7a8274; }
.workout-library { margin-top: 24px; overflow: visible; }
.workout-library-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 22px 22px 0; }
.workout-library-heading h2 { font-size: 16px; font-weight: 650; }.workout-library-heading p { font-size: 12px; color: var(--wn-muted); margin-top: 5px; line-height: 1.5; }
.workout-result-count { font-size: 11px; color: var(--wn-muted); white-space: nowrap; }
.workout-filters { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(150px, .8fr) minmax(150px, .8fr) auto; align-items: end; gap: 12px; padding: 20px 22px; border-bottom: 1px solid var(--wn-line); }
.workout-search { height: 43px; }.workout-search svg { color: var(--wn-muted); flex-shrink: 0; }.workout-filter { display: grid; gap: 6px; min-width: 0; }.workout-filter > span { font-size: 11px; font-weight: 600; color: var(--wn-muted); }.workout-clear { min-height: 43px; }
.workout-row { display: flex; align-items: center; gap: 16px; padding: 22px; border-bottom: 1px solid var(--wn-line); }.workout-row:last-child { border-bottom: 0; }.workout-row:hover { background: #fbfcf9; }.workout-row-icon { display: grid; place-items: center; flex-shrink: 0; width: 42px; height: 42px; border-radius: 12px; background: var(--wn-surface-soft); color: var(--wn-primary-strong); }
.workout-row-copy { min-width: 0; flex: 1; }.workout-row-title { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }.workout-row-title h3 { font-size: 15px; font-weight: 650; line-height: 1.5; overflow-wrap: anywhere; }.workout-row-title a:hover { color: var(--wn-primary-strong); }.workout-row-subtitle { margin-top: 3px; color: var(--wn-muted); font-size: 12px; text-transform: capitalize; }.workout-row-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 16px; margin-top: 10px; color: var(--wn-muted); font-size: 11px; }.workout-row-meta > span { display: flex; align-items: center; gap: 5px; }
.workout-row-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }.workout-row-actions > .btn-primary, .workout-row-actions > .btn-secondary { min-height: 38px; font-size: 12px; }.workout-more { position: relative; }.workout-more-button { display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--wn-line); border-radius: 10px; color: var(--wn-muted); cursor: pointer; list-style: none; }.workout-more-button::-webkit-details-marker { display: none; }.workout-more[open] .workout-more-button { background: var(--wn-surface-soft); }.workout-menu { position: absolute; z-index: 20; top: calc(100% + 6px); right: 0; display: grid; min-width: 185px; padding: 6px; background: white; border: 1px solid var(--wn-line); border-radius: 12px; box-shadow: 0 8px 24px #1b2a141a; }.workout-menu > * { display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 8px; font-size: 12px; text-align: left; }.workout-menu > *:hover { background: var(--wn-surface-soft); }
.workout-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; min-height: 280px; padding: 36px 24px; text-align: center; color: var(--wn-muted); font-size: 13px; }.workout-empty h3 { color: var(--wn-ink); font-size: 18px; font-weight: 650; }.workout-empty p { max-width: 380px; line-height: 1.7; }.workout-empty-icon { display: grid; place-items: center; width: 56px; height: 56px; border-radius: 16px; background: var(--wn-primary-soft); color: var(--wn-primary-strong); }
.workout-assignment { width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; }.workout-assignment-heading { display: flex; justify-content: space-between; align-items: start; gap: 16px; }.workout-dialog-eyebrow { color: var(--wn-primary-strong); font-size: 10px; font-weight: 650; letter-spacing: .08em; }.workout-assignment-heading h2 { margin-top: 5px; font-size: 19px; font-weight: 700; }.workout-assignment-description { margin-top: 8px; font-size: 12px; color: var(--wn-muted); }.workout-assignment-note { margin-top: 20px; padding: 13px; border-radius: 11px; background: var(--wn-surface-soft); font-size: 12px; line-height: 1.7; color: var(--wn-muted); }.workout-assignment-footer { margin-top: 20px; display: flex; justify-content: end; gap: 10px; }
@media (max-width: 1100px) { .workout-filters { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; }.workout-search { grid-column: 1 / -1; }.workout-updated { display: none !important; } }
@media (max-width: 767px) { .workout-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }.workout-metric { min-height: 68px; gap: 9px; padding: 14px; }.workout-metric > strong { font-size: 21px; }.workout-library-heading { padding: 18px 18px 0; }.workout-library-heading p { max-width: 230px; }.workout-filters { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; padding: 18px; }.workout-clear { grid-column: 1 / -1; justify-self: end; min-height: 34px; padding: 6px 10px; }.workout-row { flex-wrap: wrap; padding: 18px; gap: 12px; }.workout-row-icon { display: none; }.workout-row-copy { flex-basis: calc(100% - 10px); }.workout-row-actions { width: 100%; justify-content: space-between; }.workout-row-actions > .btn-primary, .workout-row-actions > .btn-secondary { flex: 1; justify-content: center; }.workout-row-title h3 { font-size: 14px; }.workout-row-meta { gap: 8px 12px; }.workout-empty { min-height: 260px; }.workout-assignment-footer { flex-direction: column-reverse; }.workout-assignment-footer button { justify-content: center; } }
</style>
