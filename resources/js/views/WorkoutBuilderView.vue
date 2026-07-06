<template>
    <AppShell eyebrow="Treinos > Montador" :title="isEditing ? 'Editar treino' : 'Novo treino'" description="Organize a rotina por dias e detalhe a execucao de cada exercicio.">
        <template #page-actions>
            <div class="flex flex-wrap justify-end gap-3">
                <RouterLink to="/treinos" class="btn-secondary justify-center">Cancelar</RouterLink>
                <button class="btn-secondary justify-center" :disabled="saving" @click="save('rascunho')">Salvar rascunho</button>
                <button class="btn-primary justify-center" :disabled="saving" @click="save('ativo')">{{ saving ? 'Salvando...' : 'Salvar e ativar' }}</button>
            </div>
        </template>

        <div class="grid gap-5 xl:grid-cols-[330px_1fr]">
            <aside class="space-y-5">
                <section class="panel-card">
                    <h2 class="text-lg font-semibold">Informacoes do treino</h2>
                    <div class="mt-5 space-y-4">
                        <Field label="Nome do treino *" v-model="form.nome" placeholder="Ex.: Hipertrofia ABC" />
                        <Field label="Objetivo *" v-model="form.objetivo" placeholder="Ex.: Hipertrofia" />
                        <label class="block space-y-2"><span class="text-sm font-medium text-[var(--wn-muted)]">Nivel *</span><select v-model="form.nivel" class="form-control"><option value="iniciante">Iniciante</option><option value="intermediario">Intermediario</option><option value="avancado">Avancado</option></select></label>
                        <div class="grid grid-cols-2 gap-3"><Field label="Sessoes/semana" v-model="form.sessoes_semana" type="number" /><Field label="Duracao (semanas)" v-model="form.duracao_semanas" type="number" /></div>
                        <label class="block space-y-2"><span class="text-sm font-medium text-[var(--wn-muted)]">Descricao</span><textarea v-model="form.descricao" rows="4" class="form-control" placeholder="Orientacoes gerais do programa."></textarea></label>
                    </div>
                </section>
                <section class="panel-card">
                    <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Dias do treino</h2><button class="icon-button" type="button" title="Adicionar dia" @click="addDay"><Plus class="h-4 w-4" /></button></div>
                    <div class="mt-4 space-y-2">
                        <button v-for="(day, index) in form.days" :key="day.key" type="button" class="flex w-full items-center justify-between rounded-lg border px-3 py-3 text-left text-sm" :class="activeDay === index ? 'border-[var(--wn-primary-strong)] bg-[var(--wn-primary-soft)]' : 'border-[var(--wn-line)]'" @click="activeDay = index"><span class="font-semibold">{{ day.name || `Treino ${index + 1}` }}</span><span class="text-xs text-[var(--wn-muted)]">{{ day.exercises.length }} exerc.</span></button>
                    </div>
                </section>
            </aside>

            <main v-if="currentDay" class="space-y-5">
                <section class="panel-card">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end">
                        <div class="grid flex-1 gap-4 sm:grid-cols-2"><Field label="Nome do dia *" v-model="currentDay.name" placeholder="Ex.: Treino A" /><Field label="Foco" v-model="currentDay.focus" placeholder="Ex.: Peito e triceps" /></div>
                        <button v-if="form.days.length > 1" class="btn-secondary text-rose-600" type="button" @click="removeDay(activeDay)"><Trash2 class="h-4 w-4" />Remover dia</button>
                    </div>
                </section>

                <section class="panel-card p-0">
                    <div class="flex items-center justify-between border-b border-[var(--wn-line)] p-5"><div><h2 class="text-lg font-semibold">Exercicios</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Defina volume, intensidade e descanso.</p></div><button class="btn-primary" type="button" @click="addExercise"><Plus class="h-4 w-4" />Adicionar exercicio</button></div>
                    <div class="divide-y divide-[var(--wn-line)]">
                        <article v-for="(exercise, index) in currentDay.exercises" :key="exercise.key" class="p-5">
                            <div class="flex items-center gap-3">
                                <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--wn-neutral-soft)] text-sm font-bold">{{ index + 1 }}</div>
                                <button type="button" class="flex min-h-[4.5rem] flex-1 items-center justify-between rounded-xl border border-[var(--wn-line)] px-4 text-left transition hover:border-[var(--wn-primary-strong)] hover:bg-[var(--wn-primary-soft)]" @click="openExercisePicker(index)">
                                    <span><strong class="block">{{ exercise.name || 'Selecionar exercicio' }}</strong><small class="mt-1 block text-[var(--wn-muted)]">{{ exercise.name ? [exercise.muscleGroup, exercise.equipment].filter(Boolean).join(' · ') : 'Buscar no catalogo interno' }}</small></span>
                                    <Search class="h-5 w-5 shrink-0 text-[var(--wn-primary-strong)]" />
                                </button>
                                <button class="icon-button shrink-0 text-rose-600" type="button" title="Remover exercicio" @click="removeExercise(index)"><Trash2 class="h-4 w-4" /></button>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><Field label="Series" v-model="exercise.sets" type="number" /><Field label="Repeticoes" v-model="exercise.repetitions" placeholder="8-12" /><Field label="Carga" v-model="exercise.load" placeholder="A definir" /><Field label="Descanso (seg.)" v-model="exercise.restSeconds" type="number" /></div>
                            <div class="mt-3"><Field label="Observacoes" v-model="exercise.notes" placeholder="Cadencia, tecnica ou adaptacoes." /></div>
                        </article>
                    </div>
                </section>
                <p v-if="message" class="rounded-lg px-4 py-3 text-sm" :class="hasError ? 'bg-rose-50 text-rose-600' : 'bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]'">{{ message }}</p>
            </main>
        </div>

        <div v-if="pickerExerciseIndex !== null" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @click.self="closePicker">
            <section class="flex max-h-[82vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex items-start justify-between border-b border-[var(--wn-line)] p-5"><div><h2 class="text-xl font-semibold">Selecionar exercicio</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Escolha um movimento do catalogo WNFit.</p></div><button class="icon-button" type="button" @click="closePicker"><X class="h-4 w-4" /></button></header>
                <div class="grid gap-3 border-b border-[var(--wn-line)] p-5 sm:grid-cols-[1fr_180px]">
                    <label class="input-shell"><Search class="h-5 w-5 text-[var(--wn-muted)]" /><input v-model="exerciseSearch" class="auth-input" placeholder="Buscar exercicio, musculo ou equipamento..." /></label>
                    <select v-model="muscleFilter" class="form-control"><option value="todos">Todos os musculos</option><option v-for="muscle in muscleOptions" :key="muscle" :value="muscle">{{ muscle }}</option></select>
                </div>
                <div class="overflow-y-auto p-3">
                    <button v-for="exercise in filteredExercises" :key="exercise.id" type="button" class="flex w-full items-center gap-4 rounded-xl p-3 text-left transition hover:bg-[var(--wn-primary-soft)]" @click="selectExercise(exercise)">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[var(--wn-neutral-soft)] text-[var(--wn-primary-strong)]"><Dumbbell class="h-5 w-5" /></div>
                        <div class="min-w-0 flex-1"><p class="font-semibold">{{ exercise.name }}</p><p class="mt-1 text-sm text-[var(--wn-muted)]">{{ exercise.muscleGroup }}<span v-if="exercise.equipment"> · {{ exercise.equipment }}</span></p></div>
                        <span v-if="exercise.custom" class="badge-muted">Personalizado</span>
                        <ChevronRight class="h-5 w-5 shrink-0 text-[var(--wn-muted)]" />
                    </button>
                    <p v-if="!filteredExercises.length" class="p-8 text-center text-sm text-[var(--wn-muted)]">Nenhum exercicio encontrado no catalogo.</p>
                </div>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ChevronRight, Dumbbell, Plus, Search, Trash2, X } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';

const Field = defineComponent({ props: { label: String, modelValue: [String, Number], type: { type: String, default: 'text' }, placeholder: String }, emits: ['update:modelValue'], setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label), h('input', { value: props.modelValue ?? '', type: props.type, placeholder: props.placeholder, class: 'form-control', onInput: (event) => emit('update:modelValue', event.target.value) })]) });
const newExercise = () => ({ key: crypto.randomUUID(), exerciseId: null, name: '', muscleGroup: '', equipment: '', sets: 3, repetitions: '10', load: '', restSeconds: 60, notes: '' });
const newDay = (number) => ({ key: crypto.randomUUID(), name: `Treino ${String.fromCharCode(64 + number)}`, focus: '', exercises: [newExercise()] });
const route = useRoute(); const router = useRouter(); const saving = ref(false); const message = ref(''); const hasError = ref(false); const activeDay = ref(0);
const catalog = ref([]); const muscleOptions = ref([]); const exerciseSearch = ref(''); const muscleFilter = ref('todos'); const pickerExerciseIndex = ref(null);
const isEditing = computed(() => Boolean(route.params.id));
const form = reactive({ nome: '', objetivo: '', nivel: 'iniciante', sessoes_semana: 3, duracao_semanas: 4, descricao: '', days: [newDay(1)] });
const currentDay = computed(() => form.days[activeDay.value]);
const filteredExercises = computed(() => { const term = exerciseSearch.value.trim().toLocaleLowerCase('pt-BR'); return catalog.value.filter((exercise) => (muscleFilter.value === 'todos' || exercise.muscleGroup === muscleFilter.value) && (!term || `${exercise.name} ${exercise.muscleGroup} ${exercise.equipment ?? ''}`.toLocaleLowerCase('pt-BR').includes(term))); });
const addDay = () => { form.days.push(newDay(form.days.length + 1)); activeDay.value = form.days.length - 1; };
const removeDay = (index) => { form.days.splice(index, 1); activeDay.value = Math.max(0, index - 1); };
const addExercise = () => currentDay.value.exercises.push(newExercise());
const removeExercise = (index) => { if (currentDay.value.exercises.length > 1) currentDay.value.exercises.splice(index, 1); };
const openExercisePicker = (index) => { pickerExerciseIndex.value = index; exerciseSearch.value = ''; muscleFilter.value = 'todos'; };
const closePicker = () => { pickerExerciseIndex.value = null; };
const selectExercise = (selected) => { Object.assign(currentDay.value.exercises[pickerExerciseIndex.value], { exerciseId: selected.id, name: selected.name, muscleGroup: selected.muscleGroup, equipment: selected.equipment ?? '' }); closePicker(); };
const payload = (status) => ({ ...form, status, days: form.days.map(({ key, ...day }) => ({ ...day, exercises: day.exercises.map(({ key: exerciseKey, ...exercise }) => exercise) })) });
const save = async (status) => { saving.value = true; message.value = ''; hasError.value = false; try { const request = isEditing.value ? window.axios.put(`/api/workouts/${route.params.id}`, payload(status)) : window.axios.post('/api/workouts', payload(status)); const { data } = await request; router.push(`/treinos/${data.workout.id}/editar`); message.value = status === 'ativo' ? 'Treino salvo e ativado.' : 'Rascunho salvo.'; } catch (exception) { const errors = exception.response?.data?.errors; hasError.value = true; message.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar o treino.'; } finally { saving.value = false; } };
onMounted(async () => { const catalogResponse = await window.axios.get('/api/exercises'); catalog.value = catalogResponse.data.exercises; muscleOptions.value = catalogResponse.data.filters.muscles; if (!isEditing.value) return; const { data } = await window.axios.get(`/api/workouts/${route.params.id}`); Object.assign(form, { nome: data.workout.name, objetivo: data.workout.objective, nivel: data.workout.level, sessoes_semana: data.workout.sessionsPerWeek, duracao_semanas: data.workout.durationWeeks, descricao: data.workout.description ?? '', days: data.workout.days.map((day) => ({ ...day, key: crypto.randomUUID(), exercises: day.exercises.map((exercise) => ({ ...exercise, equipment: catalog.value.find((item) => item.id === exercise.exerciseId)?.equipment ?? '', key: crypto.randomUUID() })) })) }); });
</script>
