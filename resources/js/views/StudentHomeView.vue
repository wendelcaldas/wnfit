<template>
    <StudentShell>
        <div class="mb-7">
            <h1 class="mt-2 font-display text-3xl font-bold">
                Olá, {{ firstName }}
            </h1>
            <p class="mt-2 text-sm text-[var(--wn-muted)]">
                Vamos no seu ritmo.
            </p>
        </div>
        <p v-if="loading" class="student-card" role="status">
            Preparando seu espaço…
        </p>
        <div v-else-if="error" class="student-error" role="alert">
            {{ error }}
            <button class="underline" @click="load">Tentar novamente</button>
        </div>
        <template v-else>
            <section
                v-if="data.activeSession || data.plan"
                class="student-hero"
            >
                <span class="student-overline">{{
                    data.activeSession ? "VOCÊ JÁ COMEÇOU" : "SUA FICHA ATUAL"
                }}</span>
                <h2 class="mt-4 font-display text-3xl font-bold">
                    {{ data.activeSession?.snapshot.name ?? data.plan.name }}
                </h2>
                <p class="mt-3 text-sm leading-6 text-white/75">
                    {{
                        data.activeSession
                            ? "Seu progresso está salvo. Continue de onde parou."
                            : `${data.plan.objective} · ${data.plan.sessionsPerWeek} treinos por semana`
                    }}
                </p>
                <RouterLink
                    :to="
                        data.activeSession
                            ? `/aluno/sessao/${data.activeSession.id}`
                            : '/aluno/treinos'
                    "
                    class="btn-primary mt-7 justify-center"
                    >{{
                        data.activeSession
                            ? "Continuar treino"
                            : "Começar meu treino"
                    }}<ArrowRight :size="18"
                /></RouterLink>
            </section>
            <section v-else class="student-card !p-7">
                <div class="student-icon"><Dumbbell :size="24" /></div>
                <h2 class="mt-5 text-xl font-bold">
                    Seu treino está a caminho.
                </h2>
                <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                    Seu professor precisa publicar uma ficha vigente para você.
                    Assim que estiver pronta, ela aparece aqui.
                </p>
            </section>
            <section class="student-week mt-7">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold">Sua semana</h2>
                    <p class="text-sm text-[var(--wn-muted)]">
                        {{ data.weekCompleted }}
                        <template v-if="weeklyGoal"
                            >de {{ weeklyGoal }}</template
                        >
                        {{
                            data.weekCompleted === 1 && !weeklyGoal
                                ? "treino concluído"
                                : "treinos concluídos"
                        }}
                    </p>
                </div>
                <div
                    v-if="weeklyGoal"
                    class="student-week-track"
                    role="progressbar"
                    aria-label="Treinos concluídos nesta semana"
                    :aria-valuenow="Math.min(data.weekCompleted, weeklyGoal)"
                    :aria-valuemax="weeklyGoal"
                    :aria-valuemin="0"
                >
                    <span
                        :style="{
                            width: `${Math.min(data.weekCompleted / weeklyGoal, 1) * 100}%`,
                        }"
                    ></span>
                </div>
                <p class="mt-3 text-xs text-[var(--wn-muted)]">
                    Cada sessão conta para sua constância.
                </p>
            </section>
            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Últimas conquistas</h2>
                    <RouterLink
                        to="/aluno/treinos"
                        class="text-sm font-semibold text-[var(--wn-primary-strong)]"
                        >Ver treinos</RouterLink
                    >
                </div>
                <div v-if="data.history.length" class="mt-4 space-y-3">
                    <RouterLink
                        v-for="session in data.history.slice(0, 3)"
                        :key="session.id"
                        :to="`/aluno/sessao/${session.id}`"
                        class="student-card student-history-card flex items-center gap-4"
                        ><span class="student-icon"><Check :size="18" /></span>
                        <div>
                            <strong class="text-sm">{{
                                session.snapshot.name
                            }}</strong>
                            <p class="mt-1 text-xs text-[var(--wn-muted)]">
                                {{ date(session.finished_at) }} · Concluído
                            </p>
                        </div>
                        <ChevronRight class="ml-auto" :size="18"
                    /></RouterLink>
                </div>
                <p v-else class="mt-4 text-sm leading-6 text-[var(--wn-muted)]">
                    Seu histórico começa quando você concluir o primeiro treino.
                </p>
            </section>
        </template>
    </StudentShell>
</template>
<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import { ArrowRight, Check, ChevronRight, Dumbbell } from "lucide-vue-next";
import StudentShell from "../components/StudentShell.vue";
import { useStudentAuthStore } from "../stores/studentAuth";
const auth = useStudentAuthStore();
const firstName = computed(() => auth.student?.name.split(" ")[0]);
const data = ref({ history: [] });
const weeklyGoal = computed(
    () => Number(data.value.plan?.sessionsPerWeek) || 0,
);
const loading = ref(true);
const error = ref("");
const date = (value) =>
    new Date(value).toLocaleDateString("pt-BR", {
        day: "numeric",
        month: "long",
    });
async function load() {
    loading.value = true;
    error.value = "";
    try {
        data.value = (await window.axios.get("/api/student/home")).data;
    } catch {
        error.value = "Não conseguimos carregar seus treinos.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
