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
            <article class="panel-card min-w-0">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Frequência dos alunos</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Visão de atividade e frequência recente dos alunos.</p>
                    </div>
                    <button class="btn-secondary">7 dias</button>
                </div>

                <div class="mt-8">
                    <div class="dashboard-line-chart">
                        <div class="absolute inset-x-0 top-0 flex flex-col gap-[2.35rem]">
                            <div v-for="line in 5" :key="line" class="border-t border-[var(--wn-line)]"></div>
                        </div>

                        <svg viewBox="0 0 600 220" preserveAspectRatio="none" class="absolute inset-0 h-full w-full">
                            <defs>
                                <linearGradient id="checkinArea" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#8fd400" stop-opacity="0.35" />
                                    <stop offset="100%" stop-color="#8fd400" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <polygon :points="chartAreaPoints" fill="url(#checkinArea)" />
                            <polyline :points="chartLinePoints" fill="none" stroke="#5aa400" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            <circle v-for="point in chartSvgPoints" :key="point.label" :cx="point.x" :cy="point.y" r="5" fill="#5aa400" />
                        </svg>

                        <div class="absolute inset-x-0 bottom-0 grid" :style="{ gridTemplateColumns: `repeat(${chart.length}, minmax(0, 1fr))` }">
                            <span v-for="point in chart" :key="point.day" class="text-center text-xs font-medium text-[var(--wn-muted)]">{{ point.day }}</span>
                        </div>
                    </div>
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
import { computed, onMounted, ref } from 'vue';
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

const clients = ref([]);


const chartSvgPoints = computed(() => {
    if (!chart.value.length) {
        return [];
    }

    const max = Math.max(...chart.value.map((point) => point.value), 1);
    const step = chart.value.length > 1 ? 600 / (chart.value.length - 1) : 600;

    return chart.value.map((point, index) => ({
        label: point.day,
        x: index * step,
        y: 190 - (point.value / max) * 145,
    }));
});

const chartLinePoints = computed(() => chartSvgPoints.value.map((point) => `${point.x},${point.y}`).join(' '));
const chartAreaPoints = computed(() => (chartLinePoints.value ? `0,220 ${chartLinePoints.value} 600,220` : ''));

onMounted(async () => {
    const { data } = await window.axios.get('/api/dashboard');

    stats.value = data.stats.map((stat) => ({
        ...stat,
        ...(statMeta[stat.type] ?? statMeta.students),
    }));
    chart.value = data.chart;

    clients.value = data.clients;

});
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
@media (min-width: 768px) {
    .dashboard-calendar-row { grid-template-columns: minmax(0, 1fr) minmax(15rem, 19rem); align-items: stretch; }
}
</style>
