<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="panel-card">
            <h2 class="text-lg font-semibold">Sessões do aluno</h2>
            <p class="mt-2 text-xs text-[var(--wn-muted)]">
                Últimas 30 sessões, incluindo as que estão em andamento.
            </p>
            <div v-if="sessions.length" class="mt-5 space-y-3">
                <details
                    v-for="session in sessions"
                    :key="session.id"
                    class="rounded-xl border border-[var(--wn-line)] p-4"
                >
                    <summary class="cursor-pointer text-sm">
                        <strong>{{ session.snapshot.name }}</strong
                        ><span class="mt-2 block text-xs text-[var(--wn-muted)]"
                            >{{ date(session.started_at) }} ·
                            {{ status(session.status) }} ·
                            {{
                                session.progress.filter((p) => p.done).length
                            }}
                            séries</span
                        >
                    </summary>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(exercise, ei) in session.snapshot.exercises"
                            :key="ei"
                        >
                            <strong class="text-xs">{{ exercise.name }}</strong>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="row in session.progress.filter(
                                        (p) => p.exerciseIndex === ei && p.done,
                                    )"
                                    :key="row.setIndex"
                                    class="rounded-lg bg-[var(--wn-primary-soft)] px-3 py-2 text-xs"
                                    >Série {{ row.setIndex + 1 }} ·
                                    {{ row.load || "Carga não registrada" }} ·
                                    {{ row.repetitions ?? "—" }} rep.</span
                                >
                            </div>
                        </div>
                    </div>
                </details>
            </div>
            <p v-else class="mt-5 text-sm text-[var(--wn-muted)]">
                Nenhuma sessão registrada ainda.
            </p>
        </section>
        <section class="panel-card">
            <h2 class="text-lg font-semibold">Histórico de fichas</h2>
            <div v-if="plans.length" class="mt-5 space-y-3">
                <details
                    v-for="plan in plans"
                    :key="plan.assignmentId"
                    class="rounded-xl border border-[var(--wn-line)] p-4"
                >
                    <summary class="cursor-pointer text-sm">
                        <strong>{{ plan.name }}</strong
                        ><span class="mt-2 block text-xs text-[var(--wn-muted)]"
                            >{{ date(plan.startsOn)
                            }}{{
                                plan.endsOn ? ` até ${date(plan.endsOn)}` : ""
                            }}
                            ·
                            {{
                                plan.status === "ativo"
                                    ? "Publicada"
                                    : "Substituída"
                            }}</span
                        >
                    </summary>
                    <div v-for="day in plan.days" :key="day.id" class="mt-4">
                        <h3 class="text-sm font-bold">
                            {{ day.name }} · {{ day.focus }}
                        </h3>
                        <p
                            v-for="e in day.exercises"
                            :key="e.id"
                            class="mt-2 text-xs leading-5"
                        >
                            {{ e.name }} · {{ e.sets }} × {{ e.repetitions }} ·
                            {{ e.load || "A definir" }} · {{ e.restSeconds }}s
                        </p>
                    </div>
                </details>
            </div>
            <p v-else class="mt-5 text-sm text-[var(--wn-muted)]">
                As fichas publicadas ficam guardadas aqui.
            </p>
        </section>
    </div>
</template>
<script setup>
defineProps({
    plans: { type: Array, default: () => [] },
    sessions: { type: Array, default: () => [] },
});
const date = (value) =>
    new Date(
        value?.length === 10 ? `${value}T12:00:00` : value,
    ).toLocaleDateString("pt-BR");
const status = (value) =>
    ({
        in_progress: "Em andamento",
        completed: "Concluída",
        abandoned: "Encerrada sem concluir",
    })[value] ?? value;
</script>
