<template>
    <StudentShell>
        <span class="student-overline">SEU PROGRAMA</span>
        <h1 class="mt-2 font-display text-3xl font-bold">Meus treinos</h1>
        <p v-if="loading" class="student-card mt-6" role="status">
            Carregando sua ficha…
        </p>
        <div v-else-if="error" class="student-error mt-5" role="alert">
            {{ error }}
            <button class="underline" @click="load">Tentar novamente</button>
        </div>
        <template v-else>
            <RouterLink
                v-if="data.activeSession"
                :to="`/aluno/sessao/${data.activeSession.id}`"
                class="mt-5 flex items-center justify-between gap-4 rounded-2xl bg-[var(--wn-primary-soft)] p-5 font-semibold text-[var(--wn-primary-strong)]"
                >Continuar {{ data.activeSession.snapshot.name
                }}<ArrowRight :size="20"
            /></RouterLink>
            <section v-if="data.plan" class="mt-6">
                <div class="student-card">
                    <span class="badge-success">Ficha vigente</span>
                    <h2 class="mt-3 text-xl font-bold">{{ data.plan.name }}</h2>
                    <p class="mt-2 text-sm text-[var(--wn-muted)]">
                        {{ data.plan.objective }} ·
                        {{ data.plan.sessionsPerWeek }}x por semana
                    </p>
                    <p class="mt-3 text-xs text-[var(--wn-muted)]">
                        Por {{ data.plan.author }}
                        <span v-if="data.plan.endsOn"
                            >· Até {{ date(data.plan.endsOn) }}</span
                        >
                    </p>
                    <p
                        v-if="data.plan.description"
                        class="mt-4 border-t border-[var(--wn-line)] pt-4 text-sm leading-6"
                    >
                        {{ data.plan.description }}
                    </p>
                </div>
                <div
                    class="mt-5 flex gap-2 overflow-x-auto pb-2"
                    role="tablist"
                    aria-label="Divisões do treino"
                >
                    <button
                        v-for="(day, index) in data.plan.days"
                        :key="index"
                        role="tab"
                        :aria-selected="activeDay === index"
                        class="student-day-tab"
                        :class="{ active: activeDay === index }"
                        @click="activeDay = index"
                    >
                        {{ day.name
                        }}<span>{{ day.exercises.length }} exercícios</span>
                    </button>
                </div>
                <div v-if="day" class="mt-4">
                    <h3 class="text-lg font-bold">
                        {{ day.focus || day.name }}
                    </h3>
                    <p class="mt-1 text-sm text-[var(--wn-muted)]">
                        Siga a ordem e respeite os intervalos.
                    </p>
                    <div class="mt-5 space-y-3">
                        <article
                            v-for="(exercise, index) in day.exercises"
                            :key="index"
                            class="student-card"
                        >
                            <div class="flex items-start gap-3">
                                <span class="student-exercise-number">{{
                                    index + 1
                                }}</span>
                                <div>
                                    <h4 class="font-bold">
                                        {{ exercise.name }}
                                    </h4>
                                    <p
                                        class="mt-1 text-xs text-[var(--wn-muted)]"
                                    >
                                        {{ exercise.muscleGroup }}
                                    </p>
                                </div>
                            </div>
                            <div class="student-prescription">
                                <span
                                    ><strong
                                        >{{ exercise.sets }} ×
                                        {{ exercise.repetitions }}</strong
                                    >Séries × repetições</span
                                ><span
                                    ><strong>{{
                                        exercise.load || "A definir"
                                    }}</strong
                                    >Carga</span
                                ><span
                                    ><strong>{{ exercise.restSeconds }}s</strong
                                    >Descanso</span
                                >
                            </div>
                            <WorkoutExerciseInfo :exercise="exercise" />
                        </article>
                    </div>
                    <button
                        class="btn-primary mt-6 w-full justify-center"
                        :disabled="busy || !day.exercises.length"
                        @click="start"
                    >
                        {{
                            busy
                                ? "Preparando…"
                                : data.activeSession
                                  ? "Continuar treino em andamento"
                                  : `Iniciar ${day.name}`
                        }}<ArrowRight :size="18" />
                    </button>
                    <p
                        v-if="actionError"
                        role="alert"
                        class="student-error mt-3"
                    >
                        {{ actionError }}
                    </p>
                </div>
            </section>
            <section v-else class="student-card mt-6">
                <Dumbbell class="text-[var(--wn-primary-strong)]" :size="32" />
                <h2 class="mt-4 text-xl font-bold">Ainda sem ficha vigente</h2>
                <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                    Fale com seu professor para receber ou renovar seu programa
                    de treino.
                </p>
            </section>
            <section class="mt-9">
                <h2 class="text-lg font-bold">Histórico de sessões</h2>
                <div v-if="data.history.length" class="mt-4 space-y-3">
                    <RouterLink
                        v-for="session in data.history"
                        :key="session.id"
                        :to="`/aluno/sessao/${session.id}`"
                        class="student-card flex items-center justify-between gap-4"
                        ><div>
                            <strong class="text-sm">{{
                                session.snapshot.name
                            }}</strong>
                            <p class="mt-1 text-xs text-[var(--wn-muted)]">
                                {{ date(session.finished_at) }} ·
                                {{
                                    session.progress.filter((p) => p.done)
                                        .length
                                }}
                                séries realizadas
                            </p>
                        </div>
                        <ChevronRight :size="18"
                    /></RouterLink>
                </div>
                <p v-else class="mt-3 text-sm text-[var(--wn-muted)]">
                    Seus treinos concluídos vão aparecer aqui.
                </p>
            </section>
        </template>
    </StudentShell>
</template>
<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { ArrowRight, ChevronRight, Dumbbell } from "lucide-vue-next";
import StudentShell from "../components/StudentShell.vue";
import WorkoutExerciseInfo from "../components/WorkoutExerciseInfo.vue";
const router = useRouter();
const data = ref({ history: [] });
const loading = ref(true);
const error = ref("");
const actionError = ref("");
const busy = ref(false);
const activeDay = ref(0);
const day = computed(() => data.value.plan?.days[activeDay.value]);
const date = (value) =>
    new Date(
        value.length === 10 ? `${value}T12:00:00` : value,
    ).toLocaleDateString("pt-BR");
async function load() {
    loading.value = true;
    error.value = "";
    try {
        data.value = (await window.axios.get("/api/student/home")).data;
    } catch {
        error.value = "Não conseguimos carregar sua ficha.";
    } finally {
        loading.value = false;
    }
}
async function start() {
    busy.value = true;
    actionError.value = "";
    try {
        const { data: result } = await window.axios.post(
            "/api/student/sessions",
            { dayIndex: activeDay.value },
        );
        router.push(`/aluno/sessao/${result.session.id}`);
    } catch (e) {
        actionError.value =
            e.response?.data?.message ?? "Não foi possível iniciar o treino.";
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
