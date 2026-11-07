<template>
    <AppShell
        visual-refresh
        eyebrow="Visão geral"
        :title="`Olá, ${auth.firstName}`"
        :description="`Bem-vindo(a) de volta ao painel operacional ${auth.organization?.name ? `da ${auth.organization.name}` : 'do WNFit'}.`"
        search-placeholder="Buscar alunos, treinos ou aulas..."
    >
        <section class="dashboard-metrics" aria-label="Indicadores operacionais">
            <article v-for="stat in stats" :key="stat.label" class="stat-card dashboard-metric" :title="stat.caption">
                <component :is="stat.icon" class="metric-icon" aria-hidden="true" />
                <div class="metric-copy">
                    <p class="metric-label">{{ stat.label }}</p>
                    <p class="metric-value">{{ stat.value }}</p>
                </div>
                <span class="sr-only">{{ stat.caption }}</span>
            </article>
        </section>

        <section class="dashboard-calendar-row mt-6 grid gap-4">
            <article class="panel-card min-w-0" aria-labelledby="attendance-heading">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 id="attendance-heading" class="text-xl font-semibold text-[var(--wn-ink)]">Frequência dos alunos</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Alunos que concluíram pelo menos um treino por dia.</p>
                    </div>
                    <select v-model.number="chartDays" class="attendance-period" aria-label="Período da frequência">
                        <option :value="7">7 dias</option>
                        <option :value="15">15 dias</option>
                        <option :value="30">30 dias</option>
                    </select>
                </div>

                <p v-if="chartLoading || chartError" class="attendance-status" role="status">
                    {{ chartLoading ? 'Carregando frequência…' : 'Não foi possível carregar a frequência.' }}
                </p>
                <div v-else-if="chart.length" class="attendance-chart" :class="{ 'attendance-chart-dense': chart.length > 7 }">
                    <div class="attendance-guides" aria-hidden="true">
                        <div v-for="(tick, index) in chartTicks" :key="tick" class="attendance-guide" :style="{ bottom: `${index * 25}%` }">
                            <span>{{ tick }}</span>
                        </div>
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="attendance-trend">
                            <defs>
                                <linearGradient id="attendance-fill" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--wn-primary)" stop-opacity=".25" />
                                    <stop offset="100%" stop-color="var(--wn-primary)" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <polygon :points="chartArea" fill="url(#attendance-fill)" />
                            <polyline :points="chartLine" fill="none" stroke="var(--wn-primary-strong)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                        </svg>
                    </div>

                    <ol class="attendance-days" :style="{ gridTemplateColumns: `repeat(${chart.length}, minmax(0, 1fr))` }" role="list">
                        <li v-for="(point, index) in chart" :key="point.day" class="attendance-day" :aria-current="index === chart.length - 1 ? 'date' : undefined" :title="`${point.day}: ${point.value} ${point.value === 1 ? 'aluno' : 'alunos'}`">
                            <div class="attendance-plot">
                                <div class="attendance-point" :style="{ bottom: `${point.value / chartMax * 100}%` }">
                                    <span :class="chartLabels.has(index) ? 'attendance-value' : 'sr-only'">{{ point.value }}<span class="sr-only"> {{ point.value === 1 ? 'aluno' : 'alunos' }}</span></span>
                                </div>
                            </div>
                            <span :class="chartLabels.has(index) ? 'attendance-date' : 'sr-only'">
                                <template v-if="index === chart.length - 1">Hoje<span class="sr-only">, {{ point.day }}</span></template>
                                <template v-else>{{ point.day }}</template>
                            </span>
                        </li>
                    </ol>
                </div>
            </article>

            <DashboardCalendar />
        </section>

        <section class="mt-6 grid min-w-0 gap-6">
            <article class="panel-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Alunos recentes</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Resumo dos alunos mais recentes e status atual.</p>
                    </div>
                    <RouterLink to="/alunos" class="text-sm font-semibold text-[var(--wn-primary-strong)]">Ver todos</RouterLink>
                </div>

                <div class="admin-desktop-table mt-8 overflow-x-auto rounded-[1.4rem] border border-[var(--wn-line)]">
                    <table class="min-w-full divide-y divide-[var(--wn-line)]">
                        <thead class="bg-[var(--wn-surface-soft)]">
                            <tr class="text-left text-sm font-semibold text-[var(--wn-muted)]">
                                <th class="px-5 py-4">Aluno</th>
                                <th class="px-5 py-4">Plano</th>
                                <th class="px-5 py-4">Vencimento</th>
                                <th class="px-5 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--wn-line)] bg-white">
                            <tr v-for="client in clients" :key="client.name">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-11 w-11 place-items-center rounded-2xl bg-[var(--wn-surface-soft)] text-sm font-semibold text-[var(--wn-neutral-strong)]">
                                            {{ client.initials }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-[var(--wn-ink)]">{{ client.name }}</p>
                                            <p class="text-sm text-[var(--wn-muted)]">{{ client.phone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-[var(--wn-ink)]">{{ client.plan }}</td>
                                <td class="px-5 py-4 text-sm text-[var(--wn-muted)]">{{ client.dueDate }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full bg-[var(--wn-success-soft)] px-3 py-1 text-xs font-semibold text-[var(--wn-success)]">
                                        {{ client.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <article v-for="client in clients" :key="`mobile-${client.name}`" class="admin-mobile-card">
                    <div class="flex justify-between gap-3"><strong>{{ client.name }}</strong><span class="badge-success">{{ client.status }}</span></div>
                    <dl class="admin-mobile-details"><div><dt>Plano</dt><dd>{{ client.plan }}</dd></div><div><dt>Vencimento</dt><dd>{{ client.dueDate }}</dd></div></dl>
                </article>
            </article>


        </section>
    </AppShell>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import {

    CalendarCheck,
    ClipboardList,
    Dumbbell,
    Users,
} from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';
import DashboardCalendar from '../components/DashboardCalendar.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const statMeta = {
    students: {
        icon: Users,
        iconWrapClass: 'bg-[var(--wn-primary-soft)]',
        iconClass: 'text-[var(--wn-primary-strong)]',
        badgeClass: 'bg-[var(--wn-success-soft)] text-[var(--wn-success)]',
    },
    checkins: {
        icon: Dumbbell,
        iconWrapClass: 'bg-[var(--wn-primary-soft)]',
        iconClass: 'text-[var(--wn-primary-strong)]',
        badgeClass: 'bg-[var(--wn-neutral-soft)] text-[var(--wn-neutral-strong)]',
    },
    classes: {
        icon: ClipboardList,
        iconWrapClass: 'bg-[var(--wn-primary-soft)]',
        iconClass: 'text-[var(--wn-primary-strong)]',
        badgeClass: 'bg-[var(--wn-neutral-soft)] text-[var(--wn-neutral-strong)]',
    },
    reservations: {
        icon: CalendarCheck,
        iconWrapClass: 'bg-[var(--wn-primary-soft)]',
        iconClass: 'text-[var(--wn-primary-strong)]',
        badgeClass: 'bg-[var(--wn-neutral-soft)] text-[var(--wn-neutral-strong)]',
    },
};

const stats = ref([]);
const chart = ref([]);
const chartDays = ref(7);
const chartLoading = ref(false);
const chartError = ref(false);
const clients = ref([]);

const chartLabels = computed(() => {
    const count = chart.value.length;

    if (count <= 7) {
        return new Set(chart.value.keys());
    }

    return new Set([0, 1, 2, 3, 4].map((index) => Math.round(index * (count - 1) / 4)));
});

const chartStep = computed(() => Math.max(1, Math.ceil(Math.max(0, ...chart.value.map((point) => point.value)) / 4)));
const chartMax = computed(() => chartStep.value * 4);
const chartTicks = computed(() => [0, 1, 2, 3, 4].map((index) => index * chartStep.value));
const chartPoints = computed(() => chart.value.map((point, index) => ({
    x: (index + 0.5) * 100 / chart.value.length,
    y: 100 - point.value / chartMax.value * 100,
})));
const chartLine = computed(() => chartPoints.value.map(({ x, y }) => `${x},${y}`).join(' '));
const chartArea = computed(() => `${chartPoints.value[0]?.x ?? 0},100 ${chartLine.value} ${chartPoints.value.at(-1)?.x ?? 0},100`);

watch(chartDays, async (days, _previous, onCleanup) => {
    const controller = new AbortController();
    onCleanup(() => controller.abort());
    chartLoading.value = true;
    chartError.value = false;

    try {
        const { data } = await window.axios.get('/api/dashboard', { params: { days }, signal: controller.signal });
        if (controller.signal.aborted) return;

        stats.value = data.stats.map((stat) => ({
            ...stat,
            ...(statMeta[stat.type] ?? statMeta.students),
        }));
        chart.value = data.chart;
        clients.value = data.clients;
    } catch {
        if (!controller.signal.aborted) chartError.value = true;
    } finally {
        if (!controller.signal.aborted) chartLoading.value = false;
    }
}, { immediate: true });
</script>

<style scoped>
.dashboard-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
.dashboard-metrics .dashboard-metric { display: flex; align-items: center; gap: .75rem; min-width: 0; min-height: 4.5rem; padding: 1rem; border-radius: 1rem; }
.metric-icon { flex-shrink: 0; width: 1.15rem; height: 1.15rem; color: var(--wn-primary-strong); }
.metric-copy { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: .5rem; min-width: 0; }
.metric-label { color: var(--wn-muted); font-size: .75rem; font-weight: 500; }
.metric-value { color: var(--wn-ink); font-size: 1.4rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
@media (min-width: 768px) { .dashboard-metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 359px) { .dashboard-metrics .dashboard-metric { gap: .4rem; padding: .8rem .65rem; } .metric-copy { gap: .3rem; } .metric-icon { width: 1rem; height: 1rem; } .metric-value { font-size: 1.2rem; } }
.dashboard-calendar-row { grid-template-columns: minmax(0, 1fr); }
.attendance-period {
    flex-shrink: 0;
    min-height: 2rem;
    padding: .25rem .5rem;
    border: 1px solid var(--wn-line);
    border-radius: .5rem;
    background: var(--wn-surface-soft);
    color: var(--wn-ink);
    font-size: .75rem;
    font-weight: 600;
}
.attendance-status { min-height: 11rem; margin-top: 2.75rem; color: var(--wn-muted); font-size: .875rem; }
.attendance-chart {
    --attendance-plot-height: 10rem;
    position: relative;
    margin-top: 2.75rem;
    margin-left: 1.75rem;
}
.attendance-guides {
    position: absolute;
    inset: 0 0 auto;
    height: var(--attendance-plot-height);
    pointer-events: none;
}
.attendance-guide {
    position: absolute;
    inset-inline: 0;
    border-top: 1px solid var(--wn-line);
}
.attendance-guide span {
    position: absolute;
    right: calc(100% + .375rem);
    transform: translateY(-50%);
    color: var(--wn-muted);
    font-size: .625rem;
    font-variant-numeric: tabular-nums;
}
.attendance-trend { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.attendance-days { display: grid; }
.attendance-day { min-width: 0; text-align: center; }
.attendance-plot { position: relative; height: var(--attendance-plot-height); }
.attendance-point {
    position: absolute;
    left: 50%;
    width: .5rem;
    height: .5rem;
    transform: translate(-50%, 50%);
    border: 1.5px solid var(--wn-primary-strong);
    border-radius: 50%;
    background: white;
}
.attendance-value {
    position: absolute;
    bottom: calc(100% + .375rem);
    left: 50%;
    transform: translateX(-50%);
    color: var(--wn-ink);
    font-size: .75rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.attendance-date {
    display: block;
    margin-top: .55rem;
    color: var(--wn-muted);
    font-size: .6875rem;
    line-height: 1.2;
}
.attendance-day[aria-current='date'] .attendance-point { background: var(--wn-primary-strong); }
.attendance-chart-dense .attendance-point { width: .3125rem; height: .3125rem; border-width: 1px; }
.attendance-chart-dense .attendance-date {
    position: relative;
    left: 50%;
    width: max-content;
    transform: translateX(-50%);
}
.attendance-day[aria-current='date'] :is(.attendance-value, .attendance-date) {
    color: var(--wn-primary-strong);
    font-weight: 700;
}
@media (min-width: 768px) {
    .dashboard-calendar-row { grid-template-columns: minmax(0, 1fr) minmax(15rem, 19rem); align-items: stretch; }
}
</style>
