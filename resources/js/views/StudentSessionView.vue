<template>
    <StudentShell>
        <RouterLink
            to="/aluno/treinos"
            class="inline-flex items-center gap-2 text-sm text-[var(--wn-muted)]"
            ><ArrowLeft :size="16" />Meus treinos</RouterLink
        >
        <p v-if="loading" class="student-card mt-5">Carregando sua sessão…</p>
        <p v-else-if="!session" class="student-error mt-5" role="alert">
            {{ error }}
            <button class="underline" @click="load">Tentar novamente</button>
        </p>
        <template v-else>
            <div class="mt-6">
                <span class="student-overline">{{
                    editable ? "SEU MOMENTO" : "TREINO CONCLUÍDO"
                }}</span>
                <h1 class="mt-2 font-display text-3xl font-bold">
                    {{ session.snapshot.name }}
                </h1>
                <p class="mt-2 text-sm text-[var(--wn-muted)]">
                    {{ session.snapshot.focus || session.snapshot.planName }}
                </p>
            </div>
            <div class="student-session-progress">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <strong>{{ done }} de {{ total }} séries</strong
                    ><span
                        role="status"
                        :class="
                            error ? 'text-rose-700' : 'text-[var(--wn-muted)]'
                        "
                        >{{
                            error
                                ? "Não salvo"
                                : saving
                                  ? "Salvando…"
                                  : dirty
                                    ? "Alterações pendentes"
                                    : "Progresso salvo"
                        }}</span
                    >
                </div>
                <div
                    class="mt-3 h-2 overflow-hidden rounded-full bg-[var(--wn-line)]"
                >
                    <div
                        class="h-full bg-[var(--wn-primary-strong)] transition-all"
                        :style="{
                            width: `${total ? (done / total) * 100 : 0}%`,
                        }"
                    ></div>
                </div>
            </div>
            <p v-if="error" class="student-error mt-4" role="alert">
                {{ error }}
                <button
                    v-if="conflict"
                    class="font-bold underline"
                    @click="reloadSaved"
                >
                    Carregar registro salvo</button
                ><button v-else class="font-bold underline" @click="flush">
                    Tentar salvar
                </button>
            </p>
            <div
                v-if="editable && remaining > 0"
                class="student-rest"
                role="status"
            >
                <Timer :size="22" />
                <div>
                    <strong>Descanso · {{ remaining }}s</strong>
                    <p class="text-xs">Respire. Sua próxima série vem aí.</p>
                </div>
                <button
                    class="ml-auto text-sm font-semibold"
                    @click="stopTimer"
                >
                    Pular
                </button>
            </div>
            <div class="mt-5 space-y-5">
                <article
                    v-for="(exercise, ei) in session.snapshot.exercises"
                    :key="ei"
                    class="student-card"
                >
                    <div class="flex items-start gap-3">
                        <span class="student-exercise-number">{{
                            ei + 1
                        }}</span>
                        <div>
                            <h2 class="font-bold">{{ exercise.name }}</h2>
                            <p class="mt-1 text-xs text-[var(--wn-muted)]">
                                {{ exercise.sets }} ×
                                {{ exercise.repetitions }} ·
                                {{ exercise.load || "Carga a definir" }} ·
                                Descanso {{ exercise.restSeconds }}s
                            </p>
                        </div>
                    </div>
                    <WorkoutExerciseInfo :exercise="exercise" />
                    <div class="student-set-heading">
                        <span>Série</span><span>Carga usada</span
                        ><span>Repetições</span
                        ><span class="sr-only">Concluir</span>
                    </div>
                    <div
                        v-for="(row, si) in rows[ei]"
                        :key="si"
                        class="student-set-row"
                        :class="{ done: row.done }"
                    >
                        <span class="text-center text-sm font-bold">{{
                            si + 1
                        }}</span
                        ><input
                            v-model="row.load"
                            :disabled="!editable || finishing"
                            :aria-label="`Carga de ${exercise.name}, série ${si + 1}`"
                            class="form-control"
                            placeholder="Ex.: 20 kg"
                            maxlength="50"
                            @input="changed"
                        /><input
                            v-model="row.repetitions"
                            :disabled="!editable || finishing"
                            :aria-label="`Repetições de ${exercise.name}, série ${si + 1}`"
                            class="form-control"
                            type="number"
                            min="0"
                            max="1000"
                            inputmode="numeric"
                            :placeholder="exercise.repetitions"
                            @input="changed"
                        /><button
                            class="student-set-check"
                            :disabled="!editable || finishing"
                            :aria-pressed="row.done"
                            :aria-label="`${row.done ? 'Desmarcar' : 'Concluir'} série ${si + 1} de ${exercise.name}`"
                            @click="toggle(row, exercise)"
                        >
                            <Check :size="20" />
                        </button>
                    </div>
                </article>
            </div>
            <template v-if="editable"
                ><button
                    class="btn-primary mt-6 w-full justify-center"
                    :disabled="finishing || !done"
                    @click="confirmFinish = true"
                >
                    <Check :size="18" />Finalizar treino</button
                ><button
                    class="mt-5 w-full text-sm text-[var(--wn-muted)]"
                    @click="confirmAbandon = true"
                >
                    Encerrar sem concluir
                </button></template
            >
            <div v-else class="student-card mt-6 text-center">
                <span class="student-icon mx-auto"><Check :size="26" /></span>
                <h2 class="mt-4 text-xl font-bold">Mais um passo dado.</h2>
                <p class="mt-2 text-sm text-[var(--wn-muted)]">
                    {{ done }} séries realizadas. Seu professor pode acompanhar
                    esta sessão.
                </p>
                <RouterLink
                    to="/aluno/inicio"
                    class="btn-primary mt-5 justify-center"
                    >Voltar para o início</RouterLink
                >
            </div>
            <div
                v-if="confirmFinish || confirmAbandon"
                class="student-modal-backdrop"
                @click.self="closeDialog"
            >
                <section
                    class="student-card w-full max-w-sm !p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="finish-title"
                >
                    <h2 id="finish-title" class="text-xl font-bold">
                        {{
                            confirmAbandon
                                ? "Encerrar esta sessão?"
                                : done === total
                                  ? "Treino concluído!"
                                  : "Finalizar com séries pendentes?"
                        }}
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                        {{
                            confirmAbandon
                                ? "As séries registradas serão preservadas, mas esta sessão não contará como um treino concluído."
                                : `Você realizou ${done} de ${total} séries. Seu registro será salvo no histórico.`
                        }}
                    </p>
                    <p v-if="error" class="student-error mt-3">{{ error }}</p>
                    <button
                        class="btn-primary mt-5 w-full justify-center"
                        :disabled="finishing"
                        @click="finish(confirmAbandon)"
                    >
                        {{
                            finishing
                                ? "Salvando…"
                                : confirmAbandon
                                  ? "Encerrar sessão"
                                  : "Salvar e concluir"
                        }}</button
                    ><button
                        class="btn-secondary mt-3 w-full justify-center"
                        :disabled="finishing"
                        @click="closeDialog"
                    >
                        Continuar treinando
                    </button>
                </section>
            </div>
        </template>
    </StudentShell>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import {
    onBeforeRouteLeave,
    RouterLink,
    useRoute,
    useRouter,
} from "vue-router";
import { ArrowLeft, Check, Timer } from "lucide-vue-next";
import StudentShell from "../components/StudentShell.vue";
import WorkoutExerciseInfo from "../components/WorkoutExerciseInfo.vue";
const route = useRoute();
const router = useRouter();
const session = ref(null);
const rows = ref([]);
const loading = ref(true);
const error = ref("");
const dirty = ref(false);
const saving = ref(false);
const finishing = ref(false);
const conflict = ref(false);
const confirmFinish = ref(false);
const confirmAbandon = ref(false);
const editable = computed(() => session.value?.status === "in_progress");
const done = computed(() => rows.value.flat().filter((row) => row.done).length);
const total = computed(() => rows.value.flat().length);
let saveTimer;
let inflight = null;
const remaining = ref(0);
let restDeadline = 0;
let ticker;
const timerKey = `wnfit-rest-${route.params.id}`;
const payload = () =>
    rows.value.flatMap((list, exerciseIndex) =>
        list.map((row, setIndex) => ({
            exerciseIndex,
            setIndex,
            done: row.done,
            load: row.load || null,
            repetitions:
                row.repetitions === "" || row.repetitions == null
                    ? null
                    : Number(row.repetitions),
        })),
    );
async function load() {
    loading.value = true;
    error.value = "";
    try {
        session.value = (
            await window.axios.get(`/api/student/sessions/${route.params.id}`)
        ).data.session;
        dirty.value = false;
        conflict.value = false;
        rows.value = session.value.snapshot.exercises.map((exercise, ei) =>
            Array.from({ length: exercise.sets }, (_, si) => {
                const previous = session.value.progress.find(
                    (p) => p.exerciseIndex === ei && p.setIndex === si,
                );
                return {
                    done: previous?.done ?? false,
                    load: previous?.load ?? "",
                    repetitions: previous?.repetitions ?? "",
                };
            }),
        );
    } catch {
        error.value = "Não foi possível carregar esta sessão.";
    } finally {
        loading.value = false;
    }
}
function reloadSaved() {
    if (
        window.confirm(
            "Carregar o registro salvo? Suas alterações pendentes nesta tela serão descartadas.",
        )
    ) {
        clearTimeout(saveTimer);
        load();
    }
}
function changed() {
    dirty.value = true;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(flush, 600);
}
function toggle(row, exercise) {
    row.done = !row.done;
    if (row.done && exercise.restSeconds > 0) {
        restDeadline = Date.now() + exercise.restSeconds * 1000;
        sessionStorage.setItem(timerKey, String(restDeadline));
        tick();
    }
    changed();
}
function tick() {
    remaining.value = Math.max(
        0,
        Math.ceil((restDeadline - Date.now()) / 1000),
    );
}
function stopTimer() {
    restDeadline = 0;
    sessionStorage.removeItem(timerKey);
    tick();
}
async function flush() {
    clearTimeout(saveTimer);
    if (inflight) {
        await inflight;
        if (!error.value && dirty.value) return flush();
        return !error.value;
    }
    if (!dirty.value || !editable.value) return !error.value;
    saving.value = true;
    error.value = "";
    dirty.value = false;
    const body = { progress: payload(), revision: session.value.revision };
    inflight = window.axios
        .patch(`/api/student/sessions/${session.value.id}`, body)
        .then(({ data }) => {
            session.value.revision = data.session.revision;
            return true;
        })
        .catch((e) => {
            dirty.value = true;
            conflict.value = e.response?.status === 409;
            error.value = conflict.value
                ? e.response.data.message
                : "Não conseguimos salvar. Mantenha esta página aberta e tente novamente.";
            return false;
        });
    const success = await inflight;
    inflight = null;
    saving.value = false;
    if (success && dirty.value) return flush();
    return success;
}
function closeDialog() {
    confirmFinish.value = false;
    confirmAbandon.value = false;
}
async function finish(abandon) {
    finishing.value = true;
    try {
        if (!(await flush())) return;
        session.value = (
            await window.axios.patch(
                `/api/student/sessions/${session.value.id}`,
                {
                    progress: payload(),
                    revision: session.value.revision,
                    finish: !abandon,
                    abandon,
                },
            )
        ).data.session;
        dirty.value = false;
        stopTimer();
        closeDialog();
        if (abandon) router.replace("/aluno/treinos");
    } catch (e) {
        error.value =
            e.response?.data?.message ??
            "Não foi possível concluir. Tente novamente.";
    } finally {
        finishing.value = false;
    }
}
function beforeUnload(event) {
    if (dirty.value || saving.value) {
        event.preventDefault();
        event.returnValue = "";
    }
}
onBeforeRouteLeave(async () => {
    if (!dirty.value && !saving.value) return true;
    return (
        (await flush()) ||
        window.confirm(
            "Existem alterações não salvas. Deseja sair mesmo assim?",
        )
    );
});
onMounted(() => {
    load();
    restDeadline = Number(sessionStorage.getItem(timerKey)) || 0;
    tick();
    ticker = setInterval(tick, 1000);
    window.addEventListener("beforeunload", beforeUnload);
});
onUnmounted(() => {
    clearTimeout(saveTimer);
    clearInterval(ticker);
    window.removeEventListener("beforeunload", beforeUnload);
});
</script>
