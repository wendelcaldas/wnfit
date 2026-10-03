<template>
    <AppShell
        eyebrow="Treinos / Montador"
        :title="workoutId ? 'Editar programa' : 'Montar novo treino'"
        description="Prescreva com clareza e revise como o aluno vai receber a ficha."
    >
        <div
            class="sticky top-0 z-20 mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--wn-line)] bg-white p-4 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <RouterLink
                    to="/treinos"
                    class="icon-button"
                    aria-label="Voltar"
                    ><ArrowLeft :size="18" /></RouterLink
                ><span :class="dirty ? 'badge-warning' : 'badge-muted'">{{
                    dirty ? "Alterações não salvas" : "Tudo salvo"
                }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="btn-secondary"
                    :disabled="loading"
                    @click="preview = true"
                >
                    <Eye :size="16" />Visão do aluno</button
                ><button
                    class="btn-secondary"
                    :disabled="saving || loading"
                    @click="save('rascunho')"
                >
                    Salvar rascunho</button
                ><button
                    class="btn-primary"
                    :disabled="saving || loading"
                    @click="save('ativo')"
                >
                    {{
                        saving
                            ? "Salvando…"
                            : targetStudent
                              ? "Publicar para o aluno"
                              : "Publicar modelo"
                    }}
                </button>
            </div>
        </div>
        <p v-if="loading" class="panel-card">Carregando programa…</p>
        <div v-else class="grid items-start gap-5 xl:grid-cols-[280px_1fr]">
            <aside class="space-y-4">
                <section class="panel-card !p-5">
                    <h2 class="font-semibold">Sobre o programa</h2>
                    <div class="mt-5 space-y-4">
                        <Field
                            label="Nome do treino"
                            v-model="form.nome"
                            :error="fieldError('nome')"
                            placeholder="Ex.: Hipertrofia ABC"
                        /><Field
                            label="Objetivo"
                            v-model="form.objetivo"
                            :error="fieldError('objetivo')"
                        />
                        <label class="student-field"
                            >Nível<select
                                v-model="form.nivel"
                                class="form-control mt-2"
                            >
                                <option value="iniciante">Iniciante</option>
                                <option value="intermediario">
                                    Intermediário
                                </option>
                                <option value="avancado">Avançado</option>
                            </select></label
                        >
                        <div class="grid grid-cols-2 gap-3">
                            <Field
                                label="Vezes / semana"
                                type="number"
                                v-model="form.sessoes_semana"
                                min="1"
                                max="7"
                                :error="fieldError('sessoes_semana')"
                            /><Field
                                label="Semanas"
                                type="number"
                                v-model="form.duracao_semanas"
                                min="1"
                                max="52"
                                :error="fieldError('duracao_semanas')"
                            />
                        </div>
                        <label class="student-field"
                            >Orientações gerais<textarea
                                v-model="form.descricao"
                                rows="3"
                                class="form-control mt-2"
                                placeholder="O que o aluno precisa saber antes de começar."
                            ></textarea>
                        </label>
                    </div>
                </section>
                <section class="panel-card !p-5">
                    <h2 class="font-semibold">Publicação</h2>
                    <label class="student-field mt-4"
                        >Entregar para<select
                            v-model="targetStudent"
                            class="form-control mt-2"
                        >
                            <option value="">Somente biblioteca</option>
                            <option
                                v-for="student in students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ student.name }}
                            </option>
                        </select></label
                    >
                    <p class="mt-3 text-xs leading-5 text-[var(--wn-muted)]">
                        {{
                            targetStudent
                                ? "Uma nova versão substituirá a ficha vigente do aluno, preservando o histórico."
                                : "O modelo ficará disponível para reutilizar. As fichas já entregues são preservadas."
                        }}
                    </p>
                    <p class="mt-3 text-xs text-[var(--wn-muted)]">
                        Rascunhos não são entregues ao aluno.
                    </p>
                </section>
            </aside>
            <main class="min-w-0 space-y-4">
                <section class="panel-card !p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold">Divisões do treino</h2>
                        <button
                            class="btn-secondary"
                            :disabled="form.days.length >= 7"
                            @click="addDay"
                        >
                            <Plus :size="16" />Adicionar dia
                        </button>
                    </div>
                    <div class="mt-4 flex gap-2 overflow-x-auto pb-2">
                        <button
                            v-for="(day, i) in form.days"
                            :key="day.key"
                            class="student-day-tab"
                            :class="{ active: activeDay === i }"
                            @click="activeDay = i"
                        >
                            {{ day.name
                            }}<span>{{ day.exercises.length }} exercícios</span>
                        </button>
                    </div>
                </section>
                <section v-if="currentDay" class="panel-card !p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Nome da divisão"
                            v-model="currentDay.name"
                            :error="fieldError(`days.${activeDay}.name`)"
                        /><Field
                            label="Foco muscular"
                            v-model="currentDay.focus"
                            placeholder="Ex.: Peito e tríceps"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            class="text-sm font-semibold text-[var(--wn-primary-strong)]"
                            :disabled="form.days.length >= 7"
                            @click="duplicateDay"
                        >
                            Duplicar dia</button
                        ><button
                            class="text-sm text-[var(--wn-muted)]"
                            :disabled="!activeDay"
                            @click="moveDay(-1)"
                        >
                            Mover à esquerda</button
                        ><button
                            class="text-sm text-[var(--wn-muted)]"
                            :disabled="activeDay === form.days.length - 1"
                            @click="moveDay(1)"
                        >
                            Mover à direita</button
                        ><button
                            class="ml-auto text-sm text-rose-700"
                            :disabled="form.days.length === 1"
                            @click="removeDay"
                        >
                            Remover dia
                        </button>
                    </div>
                </section>
                <section v-if="currentDay" class="panel-card !p-0">
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--wn-line)] p-5"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Prescrição de exercícios
                            </h2>
                            <p class="mt-1 text-xs text-[var(--wn-muted)]">
                                A ordem abaixo será a ordem de execução do
                                aluno.
                            </p>
                        </div>
                        <button class="btn-primary" @click="openPicker">
                            <Plus :size="16" />Adicionar exercícios
                        </button>
                    </header>
                    <div
                        v-if="currentDay.exercises.length"
                        class="divide-y divide-[var(--wn-line)]"
                    >
                        <article
                            v-for="(e, i) in currentDay.exercises"
                            :key="e.key"
                            class="p-5"
                        >
                            <div class="flex items-center gap-3">
                                <span class="student-exercise-number">{{
                                    i + 1
                                }}</span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold">{{ e.name }}</h3>
                                    <p
                                        class="mt-1 text-xs text-[var(--wn-muted)]"
                                    >
                                        {{ e.muscleGroup }}
                                    </p>
                                </div>
                                <button
                                    class="icon-button"
                                    :disabled="!i"
                                    :aria-label="`Mover ${e.name} para cima`"
                                    @click="moveExercise(i, -1)"
                                >
                                    <ArrowUp :size="16" /></button
                                ><button
                                    class="icon-button"
                                    :disabled="
                                        i === currentDay.exercises.length - 1
                                    "
                                    :aria-label="`Mover ${e.name} para baixo`"
                                    @click="moveExercise(i, 1)"
                                >
                                    <ArrowDown :size="16" /></button
                                ><button
                                    class="icon-button text-rose-700"
                                    :aria-label="`Remover ${e.name}`"
                                    @click="currentDay.exercises.splice(i, 1)"
                                >
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                            <div
                                class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4"
                            >
                                <Field
                                    label="Séries"
                                    type="number"
                                    min="1"
                                    max="20"
                                    v-model="e.sets"
                                    :error="exerciseError(i, 'sets')"
                                /><Field
                                    label="Repetições"
                                    v-model="e.repetitions"
                                    placeholder="8–12"
                                    :error="exerciseError(i, 'repetitions')"
                                /><Field
                                    label="Carga prescrita"
                                    v-model="e.load"
                                    placeholder="Ex.: 20 kg"
                                    :error="exerciseError(i, 'load')"
                                /><Field
                                    label="Descanso (s)"
                                    type="number"
                                    min="0"
                                    max="1800"
                                    v-model="e.restSeconds"
                                    :error="exerciseError(i, 'restSeconds')"
                                />
                            </div>
                            <Field
                                class="mt-3"
                                label="Orientação do professor"
                                v-model="e.notes"
                                placeholder="Ex.: Controle a descida."
                            />
                            <p
                                v-if="exerciseError(i, 'exerciseId')"
                                class="mt-2 text-sm text-rose-700"
                            >
                                {{ exerciseError(i, "exerciseId") }}
                            </p>
                        </article>
                    </div>
                    <div v-else class="p-10 text-center">
                        <Dumbbell
                            class="mx-auto text-[var(--wn-primary-strong)]"
                            :size="32"
                        />
                        <h3 class="mt-4 font-semibold">
                            Comece pelos exercícios
                        </h3>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">
                            Busque no catálogo e adicione os exercícios deste
                            dia.
                        </p>
                        <button class="btn-secondary mt-5" @click="openPicker">
                            Explorar catálogo
                        </button>
                    </div>
                    <p
                        v-if="fieldError(`days.${activeDay}.exercises`)"
                        class="student-error m-4"
                    >
                        {{ fieldError(`days.${activeDay}.exercises`) }}
                    </p>
                </section>
            </main>
        </div>
        <p
            v-if="message"
            class="mt-5 rounded-xl p-4 text-sm"
            :class="
                hasError
                    ? 'student-error'
                    : 'bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]'
            "
            role="status"
        >
            {{ message }}
        </p>
        <div
            v-if="pickerOpen"
            class="student-modal-backdrop"
            @click.self="pickerOpen = false"
        >
            <section
                class="flex max-h-[90dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white"
                role="dialog"
                aria-modal="true"
                aria-labelledby="picker-title"
            >
                <header
                    class="flex items-center justify-between border-b border-[var(--wn-line)] p-5"
                >
                    <div>
                        <h2 id="picker-title" class="text-xl font-semibold">
                            Catálogo de exercícios
                        </h2>
                        <p class="mt-1 text-xs text-[var(--wn-muted)]">
                            Adicione vários exercícios sem fechar esta janela.
                        </p>
                    </div>
                    <button
                        class="icon-button"
                        aria-label="Fechar catálogo"
                        @click="pickerOpen = false"
                    >
                        <X :size="20" />
                    </button>
                </header>
                <div
                    class="grid gap-3 border-b border-[var(--wn-line)] p-5 sm:grid-cols-[1fr_180px]"
                >
                    <label class="input-shell"
                        ><Search :size="18" /><input
                            v-model="search"
                            class="auth-input"
                            placeholder="Nome, músculo ou equipamento"
                            aria-label="Buscar exercícios" /></label
                    ><select
                        v-model="muscle"
                        class="form-control"
                        aria-label="Grupo muscular"
                    >
                        <option value="todos">Todos os grupos</option>
                        <option v-for="option in muscleOptions" :key="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
                <div class="min-h-0 overflow-y-auto p-5">
                    <p v-if="catalogLoading" class="py-10 text-center text-sm">
                        Buscando exercícios…
                    </p>
                    <p v-else-if="catalogError" class="student-error">
                        {{ catalogError }}
                        <button class="underline" @click="loadCatalog">
                            Tentar novamente
                        </button>
                    </p>
                    <div v-else class="space-y-2">
                        <button
                            v-for="e in catalog"
                            :key="e.id"
                            class="flex w-full items-center gap-3 rounded-xl border p-4 text-left"
                            :class="
                                isSelected(e.id)
                                    ? 'border-lime-300 bg-[var(--wn-primary-soft)]'
                                    : 'border-[var(--wn-line)]'
                            "
                            :disabled="isSelected(e.id)"
                            @click="selectExercise(e)"
                        >
                            <span class="student-icon !h-10 !w-10"
                                ><Dumbbell :size="18" /></span
                            ><span class="flex-1"
                                ><strong class="text-sm">{{ e.name }}</strong
                                ><span
                                    class="mt-1 block text-xs text-[var(--wn-muted)]"
                                    >{{ e.muscleGroup }} ·
                                    {{ e.equipment || "Sem equipamento" }}</span
                                ></span
                            ><Check v-if="isSelected(e.id)" :size="20" /><Plus
                                v-else
                                :size="20"
                            />
                        </button>
                        <p
                            v-if="!catalog.length"
                            class="p-8 text-center text-sm"
                        >
                            Nenhum exercício encontrado.
                        </p>
                    </div>
                </div>
                <footer
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--wn-line)] p-4"
                >
                    <div class="flex items-center gap-3">
                        <button
                            class="icon-button"
                            :disabled="page <= 1 || catalogLoading"
                            aria-label="Página anterior"
                            @click="
                                page--;
                                loadCatalog();
                            "
                        >
                            <ArrowLeft :size="16" /></button
                        ><span class="text-xs"
                            >{{ page }} /
                            {{
                                Math.max(1, Math.ceil(catalogTotal / 40))
                            }}</span
                        ><button
                            class="icon-button"
                            :disabled="
                                page * 40 >= catalogTotal || catalogLoading
                            "
                            aria-label="Próxima página"
                            @click="
                                page++;
                                loadCatalog();
                            "
                        >
                            <ArrowRight :size="16" />
                        </button>
                    </div>
                    <button class="btn-primary" @click="pickerOpen = false">
                        Concluir seleção
                    </button>
                </footer>
            </section>
        </div>
        <div
            v-if="preview"
            class="student-modal-backdrop"
            @click.self="preview = false"
        >
            <section
                class="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-2xl bg-[#f5f7f3] p-5"
                role="dialog"
                aria-modal="true"
                aria-labelledby="preview-title"
            >
                <header class="mb-5 flex items-center justify-between">
                    <h2 id="preview-title" class="font-semibold">
                        Visão do aluno
                    </h2>
                    <button
                        class="icon-button"
                        aria-label="Fechar prévia"
                        @click="preview = false"
                    >
                        <X :size="20" />
                    </button>
                </header>
                <div class="student-hero">
                    <span class="student-overline !text-lime-300"
                        >SUA FICHA</span
                    >
                    <h3 class="mt-3 text-2xl font-bold">
                        {{ form.nome || "Novo programa" }}
                    </h3>
                    <p class="mt-2 text-sm">
                        {{ form.objetivo }} · {{ form.sessoes_semana }}x por
                        semana
                    </p>
                </div>
                <p v-if="form.descricao" class="mt-4 text-sm leading-6">
                    {{ form.descricao }}
                </p>
                <div v-for="day in form.days" :key="day.key" class="mt-6">
                    <h3 class="font-bold">{{ day.name }} · {{ day.focus }}</h3>
                    <article
                        v-for="e in day.exercises"
                        :key="e.key"
                        class="student-card mt-3"
                    >
                        <h4 class="font-bold">{{ e.name }}</h4>
                        <div class="student-prescription">
                            <span
                                ><strong
                                    >{{ e.sets }} × {{ e.repetitions }}</strong
                                >Séries × repetições</span
                            ><span
                                ><strong>{{ e.load || "A definir" }}</strong
                                >Carga</span
                            ><span
                                ><strong>{{ e.restSeconds }}s</strong
                                >Descanso</span
                            >
                        </div>
                        <WorkoutExerciseInfo :exercise="e" />
                    </article>
                    <p
                        v-if="!day.exercises.length"
                        class="mt-3 text-sm text-[var(--wn-muted)]"
                    >
                        Sem exercícios neste dia.
                    </p>
                </div>
            </section>
        </div>
    </AppShell>
</template>
<script setup>
import {
    computed,
    defineComponent,
    h,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch,
} from "vue";
import {
    onBeforeRouteLeave,
    RouterLink,
    useRoute,
    useRouter,
} from "vue-router";
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Check,
    Dumbbell,
    Eye,
    Plus,
    Search,
    Trash2,
    X,
} from "lucide-vue-next";
import AppShell from "../components/AppShell.vue";
import WorkoutExerciseInfo from "../components/WorkoutExerciseInfo.vue";
const Field = defineComponent({
    props: {
        label: String,
        modelValue: [String, Number],
        type: { type: String, default: "text" },
        placeholder: String,
        error: String,
        min: String,
        max: String,
    },
    emits: ["update:modelValue"],
    setup:
        (p, { emit }) =>
        () =>
            h("label", { class: "block text-xs font-medium" }, [
                p.label,
                h("input", {
                    value: p.modelValue ?? "",
                    type: p.type,
                    placeholder: p.placeholder,
                    min: p.min,
                    max: p.max,
                    class: `form-control mt-2 ${p.error ? "!border-rose-400" : ""}`,
                    "aria-invalid": !!p.error,
                    onInput: (e) => emit("update:modelValue", e.target.value),
                }),
                p.error
                    ? h(
                          "span",
                          { class: "mt-1 block text-xs text-rose-700" },
                          p.error,
                      )
                    : null,
            ]),
});
const route = useRoute();
const router = useRouter();
const workoutId = ref(route.params.id);
const activeDay = ref(0);
const loading = ref(true);
const saving = ref(false);
const message = ref("");
const hasError = ref(false);
const errors = ref({});
const students = ref([]);
const targetStudent = ref(String(route.query.student || ""));
const preview = ref(false);
const newDay = (n) => ({
    key: crypto.randomUUID(),
    name: `Treino ${String.fromCharCode(64 + n)}`,
    focus: "",
    exercises: [],
});
const form = reactive({
    nome: "",
    objetivo: "",
    nivel: "iniciante",
    sessoes_semana: 3,
    duracao_semanas: 4,
    descricao: "",
    days: [newDay(1)],
});
const revision = ref("");
const baseline = ref("");
const formState = () => JSON.stringify({ form, target: targetStudent.value });
const dirty = computed(() => !loading.value && baseline.value !== formState());
const currentDay = computed(() => form.days[activeDay.value]);
const pickerOpen = ref(false);
const search = ref("");
const muscle = ref("todos");
const muscleOptions = ref([]);
const catalog = ref([]);
const catalogTotal = ref(0);
const page = ref(1);
const catalogLoading = ref(false);
const catalogError = ref("");
let catalogTimer;
let catalogRequest = 0;
const fieldError = (key) => errors.value[key]?.[0];
const exerciseError = (i, key) =>
    fieldError(`days.${activeDay.value}.exercises.${i}.${key}`);
function addDay() {
    if (form.days.length >= 7) return;
    form.days.push(newDay(form.days.length + 1));
    activeDay.value = form.days.length - 1;
}
function duplicateDay() {
    if (form.days.length >= 7) return;
    const copy = JSON.parse(JSON.stringify(currentDay.value));
    delete copy.id;
    copy.key = crypto.randomUUID();
    copy.name += " (cópia)";
    copy.exercises = copy.exercises.map((e) => {
        delete e.id;
        return { ...e, key: crypto.randomUUID() };
    });
    form.days.push(copy);
    activeDay.value = form.days.length - 1;
}
function removeDay() {
    if (
        form.days.length === 1 ||
        !window.confirm("Remover este dia e seus exercícios?")
    )
        return;
    form.days.splice(activeDay.value, 1);
    activeDay.value = Math.max(0, activeDay.value - 1);
}
function moveDay(delta) {
    const i = activeDay.value;
    [form.days[i], form.days[i + delta]] = [form.days[i + delta], form.days[i]];
    activeDay.value += delta;
}
function moveExercise(i, delta) {
    const list = currentDay.value.exercises;
    [list[i], list[i + delta]] = [list[i + delta], list[i]];
}
const isSelected = (id) =>
    currentDay.value.exercises.some((e) => e.exerciseId === id);
function selectExercise(e) {
    if (isSelected(e.id)) return;
    currentDay.value.exercises.push({
        key: crypto.randomUUID(),
        exerciseId: e.id,
        name: e.name,
        muscleGroup: e.muscleGroup,
        sets: 3,
        repetitions: "10",
        load: "",
        restSeconds: 60,
        notes: "",
        instructions: e.instructions,
        safetyNotes: e.safetyNotes,
        videoUrl: e.videoUrl,
    });
}
function openPicker() {
    pickerOpen.value = true;
    page.value = 1;
    loadCatalog();
}
async function loadCatalog() {
    const request = ++catalogRequest;
    catalogLoading.value = true;
    catalogError.value = "";
    try {
        const { data } = await window.axios.get("/api/exercises", {
            params: { q: search.value, muscle: muscle.value, page: page.value },
        });
        if (request !== catalogRequest) return;
        catalog.value = data.exercises;
        catalogTotal.value = data.pagination.total;
        muscleOptions.value = data.filters.muscles;
    } catch {
        if (request === catalogRequest)
            catalogError.value = "Não foi possível carregar o catálogo.";
    } finally {
        if (request === catalogRequest) catalogLoading.value = false;
    }
}
watch([search, muscle], () => {
    clearTimeout(catalogTimer);
    page.value = 1;
    catalogTimer = setTimeout(loadCatalog, 250);
});
function apply(w) {
    revision.value = w.revision;
    Object.assign(form, {
        nome: w.name,
        objetivo: w.objective,
        nivel: w.level,
        sessoes_semana: w.sessionsPerWeek,
        duracao_semanas: w.durationWeeks,
        descricao: w.description ?? "",
        days: w.days.map((day) => ({
            ...day,
            key: crypto.randomUUID(),
            exercises: day.exercises.map((e) => ({
                ...e,
                key: crypto.randomUUID(),
            })),
        })),
    });
    baseline.value = formState();
}
function payload(status) {
    return {
        ...form,
        revision: workoutId.value ? revision.value : undefined,
        status,
        studentId: targetStudent.value || undefined,
        days: form.days.map((day) => ({
            id: day.id,
            name: day.name,
            focus: day.focus,
            exercises: day.exercises.map((e) => ({
                id: e.id,
                exerciseId: e.exerciseId,
                name: e.name,
                muscleGroup: e.muscleGroup,
                sets: Number(e.sets),
                repetitions: e.repetitions,
                load: e.load,
                restSeconds: Number(e.restSeconds),
                notes: e.notes,
            })),
        })),
    };
}
async function save(status) {
    saving.value = true;
    message.value = "";
    errors.value = {};
    hasError.value = false;
    try {
        const { data } = await (workoutId.value
            ? window.axios.put(
                  `/api/workouts/${workoutId.value}`,
                  payload(status),
              )
            : window.axios.post("/api/workouts", payload(status)));
        workoutId.value = data.workout.id;
        apply(data.workout);
        if (targetStudent.value && status === "ativo") {
            await router.push(`/alunos/${targetStudent.value}?tab=workouts`);
            return;
        }
        await router.replace({
            path: `/treinos/${workoutId.value}/editar`,
            query: targetStudent.value ? { student: targetStudent.value } : {},
        });
        message.value =
            status === "rascunho"
                ? "Rascunho salvo. Você pode continuar depois."
                : "Modelo publicado na biblioteca.";
    } catch (e) {
        hasError.value = true;
        errors.value = e.response?.data?.errors ?? {};
        const first = Object.keys(errors.value).find((key) =>
            key.startsWith("days."),
        );
        if (first) activeDay.value = Number(first.split(".")[1]);
        message.value =
            Object.values(errors.value).flat()[0] ??
            e.response?.data?.message ??
            "Não foi possível salvar.";
    } finally {
        saving.value = false;
    }
}
function beforeUnload(e) {
    if (dirty.value) {
        e.preventDefault();
        e.returnValue = "";
    }
}
onBeforeRouteLeave(
    () =>
        !dirty.value ||
        window.confirm("Você tem alterações não salvas. Deseja sair?"),
);
onMounted(async () => {
    try {
        const [s, w] = await Promise.all([
            window.axios.get("/api/students"),
            workoutId.value
                ? window.axios.get(`/api/workouts/${workoutId.value}`)
                : Promise.resolve(null),
        ]);
        students.value = s.data.students;
        if (w) apply(w.data.workout);
        else baseline.value = formState();
    } catch {
        hasError.value = true;
        message.value =
            "Não foi possível carregar os dados. Recarregue esta página.";
    } finally {
        loading.value = false;
    }
    window.addEventListener("beforeunload", beforeUnload);
});
onUnmounted(() => {
    clearTimeout(catalogTimer);
    window.removeEventListener("beforeunload", beforeUnload);
});
</script>
