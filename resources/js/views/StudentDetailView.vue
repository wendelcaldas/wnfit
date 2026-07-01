<template>
    <AppShell
        eyebrow="Alunos > Detalhe"
        :title="student?.name ?? 'Aluno'"
        description="Cadastro, evolução, comunicação e controle financeiro do aluno."
        search-placeholder="Buscar alunos, treinos, planos..."
    >
        <section v-if="student" class="panel-card p-0">
            <div class="p-6">
                <RouterLink to="/alunos" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--wn-muted)]">
                    <ArrowLeft class="h-4 w-4" />
                    Voltar para alunos
                </RouterLink>

                <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-5">
                        <div class="grid h-24 w-24 place-items-center rounded-full bg-[var(--wn-neutral-soft)] text-2xl font-bold text-[var(--wn-neutral-strong)]">
                            {{ student.initials }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-3xl font-bold text-[var(--wn-ink)]">{{ student.name }}</h1>
                                <span class="badge-success">{{ student.status }}</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-5 text-sm text-[var(--wn-muted)]">
                                <span class="inline-flex items-center gap-2"><Mail class="h-4 w-4" />{{ student.email }}</span>
                                <span class="inline-flex items-center gap-2"><Phone class="h-4 w-4" />{{ student.phone }}</span>
                                <span class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ student.city }}, {{ student.state }}</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.plan }}</span>
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.teacher }}</span>
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.unit }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button class="btn-secondary gap-2"><Pencil class="h-5 w-5" />Editar aluno</button>
                        <button class="icon-button"><MoreHorizontal class="h-5 w-5" /></button>
                    </div>
                </div>
            </div>

            <nav class="flex gap-2 overflow-x-auto border-t border-[var(--wn-line)] px-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="border-b-2 px-4 py-4 text-sm font-medium transition"
                    :class="activeTab === tab.key ? 'border-[var(--wn-primary)] text-[var(--wn-primary-strong)]' : 'border-transparent text-[var(--wn-muted)] hover:text-[var(--wn-ink)]'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </section>

        <section v-if="student && activeTab === 'overview'" class="mt-5 grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="space-y-5">
                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Resumo do aluno</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-4">
                        <MiniKpi label="Check-ins este mês" value="16" caption="+14% vs mês anterior" />
                        <MiniKpi label="Último treino" value="Hoje, 08:15" caption="Funcional - A" />
                        <MiniKpi label="Frequência média" value="84%" caption="Ótima frequência" />
                        <MiniKpi label="Dias ativo" value="28" caption="Nos últimos 30 dias" />
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Observações do professor</h2>
                    <p class="mt-4 rounded-xl border border-[var(--wn-line)] bg-white p-4 text-sm leading-6 text-[var(--wn-muted)]">
                        {{ student.observations || 'Nenhuma observação cadastrada.' }}
                    </p>
                </section>
            </div>

            <aside class="space-y-5">
                <PlanCard :student="student" />
                <section class="panel-card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Financeiro</h2>
                        <button class="text-sm font-semibold text-[var(--wn-primary-strong)]" @click="activeTab = 'financial'">Ver detalhes</button>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-[var(--wn-muted)]">Total pago</p>
                            <p class="mt-1 font-bold text-[var(--wn-ink)]">{{ money(student.financial.totalPaid) }}</p>
                        </div>
                        <div>
                            <p class="text-[var(--wn-muted)]">Em aberto</p>
                            <p class="mt-1 font-bold text-[var(--wn-primary-strong)]">{{ money(student.financial.openAmount) }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section v-if="student && activeTab === 'financial'" class="mt-5 grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="space-y-5">
                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Resumo financeiro</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-5">
                        <MiniKpi label="Status financeiro" :value="student.financial.status" caption="Nenhuma pendência" />
                        <MiniKpi label="Valor mensal" :value="money(student.financial.monthlyValue)" :caption="student.plan" />
                        <MiniKpi label="Próximo vencimento" :value="student.financial.nextDueDate" caption="Cobrança recorrente" />
                        <MiniKpi label="Total pago" :value="money(student.financial.totalPaid)" caption="Histórico" />
                        <MiniKpi label="Em aberto" :value="money(student.financial.openAmount)" caption="Pendências" />
                    </div>
                </section>

                <section class="panel-card p-0">
                    <div class="flex flex-col gap-4 border-b border-[var(--wn-line)] p-5 md:flex-row md:items-center md:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Cobranças</h2>
                            <span class="badge-success">Todas</span>
                            <span class="badge-muted">Pagas</span>
                            <span class="badge-muted">Pendentes</span>
                            <span class="badge-muted">Atrasadas</span>
                        </div>
                        <button class="btn-secondary gap-2" @click="generateCharge"><Plus class="h-5 w-5" />Gerar cobrança</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[var(--wn-line)]">
                            <thead>
                                <tr class="text-left text-sm font-semibold text-[var(--wn-muted)]">
                                    <th class="px-5 py-4">Competência</th>
                                    <th class="px-5 py-4">Vencimento</th>
                                    <th class="px-5 py-4">Valor</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4">Forma de pagamento</th>
                                    <th class="px-5 py-4">Pagamento</th>
                                    <th class="px-5 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--wn-line)]">
                                <tr v-for="charge in student.financial.charges" :key="charge.id">
                                    <td class="px-5 py-4 text-sm">{{ charge.competence }}</td>
                                    <td class="px-5 py-4 text-sm">{{ charge.dueDate }}</td>
                                    <td class="px-5 py-4 text-sm">{{ money(charge.value) }}</td>
                                    <td class="px-5 py-4"><span :class="charge.statusClass">{{ labelStatus(charge.status) }}</span></td>
                                    <td class="px-5 py-4 text-sm">{{ charge.paymentMethod }}</td>
                                    <td class="px-5 py-4 text-sm text-[var(--wn-muted)]">{{ charge.paidAt }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button class="icon-button" title="Enviar cobrança" @click="sendCharge(charge.id)"><MessageCircle class="h-4 w-4" /></button>
                                            <button class="icon-button" title="Registrar pagamento" @click="payCharge(charge.id)"><ReceiptText class="h-4 w-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Histórico financeiro</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-5">
                        <article v-for="event in student.financial.timeline" :key="event.date + event.type" class="rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4">
                            <div class="mb-3 grid h-9 w-9 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                                <CheckCircle2 class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-semibold text-[var(--wn-ink)]">{{ event.title }}</p>
                            <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ event.date }}</p>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <PlanCard :student="student" />
                <section class="panel-card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Automação de cobrança</h2>
                        <button class="btn-secondary">Editar regras</button>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-[var(--wn-muted)]">
                        <AutomationRow text="Enviar lembrete 2 dias antes do vencimento" />
                        <AutomationRow text="Enviar no dia do vencimento" />
                        <AutomationRow text="Enviar cobrança após 2 dias de atraso" />
                    </div>
                    <div class="mt-5 border-t border-[var(--wn-line)] pt-5 text-sm">
                        <p class="font-semibold text-[var(--wn-ink)]">Canal principal</p>
                        <p class="mt-2 text-[var(--wn-muted)]">WhatsApp</p>
                        <p class="mt-4 font-semibold text-[var(--wn-ink)]">Mensagem padrão</p>
                        <p class="mt-2 text-[var(--wn-muted)]">Olá, sua mensalidade venceu. Segue link para pagamento.</p>
                    </div>
                </section>
            </aside>
        </section>
    </AppShell>
</template>

<script setup>
import { defineComponent, h, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { ArrowLeft, CheckCircle2, Mail, MapPin, MessageCircle, MoreHorizontal, Pencil, Phone, Plus, ReceiptText } from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';

const MiniKpi = defineComponent({
    props: { label: String, value: [String, Number], caption: String },
    setup(props) {
        return () => h('article', { class: 'rounded-xl border border-[var(--wn-line)] bg-white p-4' }, [
            h('p', { class: 'text-xs text-[var(--wn-muted)]' }, props.label),
            h('p', { class: 'mt-2 text-xl font-bold text-[var(--wn-ink)]' }, props.value),
            h('p', { class: 'mt-2 text-xs text-[var(--wn-muted)]' }, props.caption),
        ]);
    },
});

const PlanCard = defineComponent({
    props: { student: Object },
    setup(props) {
        return () => h('section', { class: 'panel-card' }, [
            h('div', { class: 'flex items-center justify-between' }, [
                h('h2', { class: 'text-xl font-semibold text-[var(--wn-ink)]' }, 'Plano e assinatura'),
                h('span', { class: 'badge-success' }, props.student.financial.subscription.status ?? 'Ativa'),
            ]),
            h('div', { class: 'mt-5 space-y-4 text-sm' }, [
                h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Plano atual'), h('p', { class: 'mt-1 font-semibold text-[var(--wn-ink)]' }, props.student.financial.subscription.plan)]),
                h('div', { class: 'grid grid-cols-2 gap-4 border-t border-[var(--wn-line)] pt-4' }, [
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Início do plano'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.subscription.startDate)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Próximo vencimento'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.nextDueDate)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Ciclo'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.subscription.cycle)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Renovação'), h('p', { class: 'mt-1 text-[var(--wn-primary-strong)] font-semibold' }, props.student.financial.subscription.autoRenew ? 'Ativada' : 'Desativada')]),
                ]),
                h('button', { class: 'btn-secondary w-full justify-center' }, 'Alterar plano'),
            ]),
        ]);
    },
});

const AutomationRow = defineComponent({
    props: { text: String },
    setup(props) {
        return () => h('div', { class: 'flex items-center justify-between gap-3' }, [
            h('span', { class: 'inline-flex items-center gap-2' }, [
                h(CheckCircle2, { class: 'h-4 w-4 text-[var(--wn-primary-strong)]' }),
                props.text,
            ]),
            h(MessageCircle, { class: 'h-4 w-4 text-[var(--wn-primary-strong)]' }),
        ]);
    },
});

const route = useRoute();
const student = ref(null);
const activeTab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Visão geral' },
    { key: 'workouts', label: 'Treinos' },
    { key: 'evaluations', label: 'Avaliações' },
    { key: 'frequency', label: 'Frequência' },
    { key: 'financial', label: 'Financeiro' },
    { key: 'communications', label: 'Comunicações' },
    { key: 'files', label: 'Arquivos' },
    { key: 'history', label: 'Histórico' },
];

const money = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const labelStatus = (status) => ({ pago: 'Pago', pendente: 'Pendente', atrasado: 'Atrasado', cancelado: 'Cancelado' }[status] ?? status);

const loadStudent = async () => {
    const { data } = await window.axios.get(`/api/students/${route.params.id}`);
    student.value = data.student;
};

const generateCharge = async () => {
    await window.axios.post(`/api/students/${student.value.id}/charges`);
    await loadStudent();
};

const sendCharge = async (id) => {
    await window.axios.post(`/api/charges/${id}/send`);
    await loadStudent();
};

const payCharge = async (id) => {
    await window.axios.post(`/api/charges/${id}/pay`);
    await loadStudent();
};

onMounted(loadStudent);
</script>
