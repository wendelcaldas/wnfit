<template>
    <AppShell
        eyebrow="Dashboard principal"
        :title="`Ola, ${auth.firstName}`"
        :description="`Bem-vindo(a) de volta ao painel operacional ${auth.organization?.name ? `da ${auth.organization.name}` : 'do WNFit'}.`"
        search-placeholder="Buscar alunos, treinos ou aulas..."
    >
        <section class="grid gap-4 xl:grid-cols-4">
            <article v-for="stat in stats" :key="stat.label" class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl" :class="stat.iconWrapClass">
                        <component :is="stat.icon" class="h-6 w-6" :class="stat.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-[var(--wn-muted)]">{{ stat.label }}</p>
                        <p class="mt-1 text-3xl font-bold text-[var(--wn-ink)]">{{ stat.value }}</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--wn-primary-strong)]">{{ stat.caption }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_0.95fr]">
            <article class="panel-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Check-ins ultimos 7 dias</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Visao de atividade e frequencia recente dos alunos.</p>
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

            <article class="panel-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Proximas aulas</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Agenda resumida do dia para a operacao.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold text-[var(--wn-neutral-strong)]">Ver agenda</a>
                </div>

                <div class="mt-8 space-y-4">
                    <div v-for="classItem in classes" :key="classItem.time" class="flex items-center gap-4 rounded-[1.25rem] border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] px-4 py-4">
                        <div class="w-14 shrink-0 text-sm font-semibold text-[var(--wn-muted)]">{{ classItem.time }}</div>
                        <div class="h-3 w-3 rounded-full" :class="classItem.dot"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-[var(--wn-ink)]">{{ classItem.name }}</p>
                            <p class="truncate text-sm text-[var(--wn-muted)]">{{ classItem.room }}</p>
                        </div>
                        <span class="rounded-full bg-[var(--wn-neutral-soft)] px-3 py-1 text-xs font-semibold text-[var(--wn-neutral-strong)]">
                            {{ classItem.slots }}
                        </span>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <article class="panel-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Clientes recentes</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Resumo dos alunos mais recentes e status atual.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold text-[var(--wn-neutral-strong)]">Ver todos</a>
                </div>

                <div class="mt-8 overflow-x-auto rounded-[1.4rem] border border-[var(--wn-line)]">
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
            </article>

            <article class="panel-card">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Receitas</h2>
                        <p class="mt-2 text-sm text-[var(--wn-muted)]">Evolucao dos ultimos 6 meses.</p>
                    </div>
                    <button class="btn-secondary">6 meses</button>
                </div>

                <div class="mt-10 flex h-72 items-end justify-between gap-4">
                    <div v-for="income in incomeBars" :key="income.label" class="flex flex-1 flex-col items-center gap-3">
                        <div class="income-column w-full max-w-14 rounded-t-[1.2rem]" :style="{ height: `${income.value}%` }"></div>
                        <span class="text-sm text-[var(--wn-muted)]">{{ income.label }}</span>
                    </div>
                </div>
            </article>
        </section>
    </AppShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    CircleDollarSign,
    ClipboardList,
    Dumbbell,
    Users,
} from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';
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
    revenue: {
        icon: CircleDollarSign,
        iconWrapClass: 'bg-[var(--wn-primary-soft)]',
        iconClass: 'text-[var(--wn-primary-strong)]',
        badgeClass: 'bg-[var(--wn-neutral-soft)] text-[var(--wn-neutral-strong)]',
    },
};

const stats = ref([]);
const chart = ref([]);
const classes = ref([]);
const clients = ref([]);
const incomeBars = ref([]);

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
    classes.value = data.classes;
    clients.value = data.clients;
    incomeBars.value = data.incomeBars;
});
</script>
