<template>
    <section>
        <p v-if="error" role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-rose-700">{{ error }} <button class="underline" @click="load(page)">Tentar novamente</button></p>
        <p v-if="notice" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ notice }}</p>
        <FinanceOverview :month="month" :summary="summary" :dashboard="dashboard" :loading="loading" :error="error" :status="status" @month="setMonth" @filter="filterCharges" @create="createOpen = true"><template #movements><FinanceMovements :month="month" :revision="movementRevision" @updated="incomeCreated" /></template></FinanceOverview>
        <section ref="chargeList" class="panel-card finance-charge-list !p-0" aria-labelledby="charges-title">
            <div class="border-b border-[var(--wn-line)] p-5">
                <h2 id="charges-title" tabindex="-1" class="text-lg font-semibold">{{ status === 'previsto' ? 'Composição do previsto' : status === 'pago' ? 'Entradas recebidas' : 'Financeiro do mês' }} <span class="finance-count">{{ total }}</span></h2>
                <p class="mt-1 text-sm text-[var(--wn-muted)]">{{ status === 'pago' ? 'Recebidas no mês, incluindo avulsas, recuperações e antecipações. O total corresponde ao indicador Recebido quando não há busca aplicada.' : 'Recebidas pela data do pagamento; a vencer e vencidas pelo vencimento.' }}</p>
                <form class="finance-filters" @submit.prevent="load(1)">
                    <label><span class="mb-1 block text-xs font-semibold">Aluno</span><input v-model="search" class="form-control" placeholder="Buscar pelo nome" /></label>
                    <label><span class="mb-1 block text-xs font-semibold">Situação</span><select v-model="status" class="form-control" @change="load(1)"><option value="todos">Todas as movimentações</option><option value="previsto">Composição do previsto</option><option value="aberto">Em aberto · a vencer</option><option value="atrasado">Vencidas</option><option value="hoje">Vencem hoje</option><option value="proximos">Próximos 7 dias</option><option value="pago">Recebidas · pagas e avulsas</option></select></label>
                    <button class="btn-secondary self-end justify-center" :disabled="loading">Buscar</button>
                </form>
            </div>
            <div class="admin-desktop-table overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[var(--wn-surface-soft)] text-[var(--wn-muted)]"><tr><th class="p-4">Pessoa / cobrança</th><th class="p-4">Vencimento</th><th class="p-4">Valor</th><th class="p-4">Situação</th><th class="p-4">Último envio registrado</th><th class="p-4 text-right">Ações</th></tr></thead>
                    <tbody class="divide-y divide-[var(--wn-line)]">
                        <tr v-if="loading"><td colspan="6" class="p-10 text-center text-[var(--wn-muted)]">Carregando financeiro…</td></tr>
                        <tr v-else-if="error"><td colspan="6" class="p-10 text-center text-rose-700">Listagem indisponível. Use Tentar novamente acima.</td></tr>
                        <tr v-else-if="!charges.length"><td colspan="6" class="p-10 text-center"><p class="font-semibold">Nenhuma cobrança neste filtro</p><p class="mt-2 text-[var(--wn-muted)]">Escolha outro mês ou situação.</p></td></tr>
                        <template v-else><tr v-for="charge in charges" :key="charge.id">
                            <td class="p-4"><RouterLink v-if="charge.studentId" :to="`/alunos/${charge.studentId}?tab=financial`" class="font-semibold text-[var(--wn-primary-strong)]">{{ charge.student }}</RouterLink><strong v-else>{{ charge.student }}</strong><p class="mt-1 text-xs text-[var(--wn-muted)]">{{ charge.plan }}</p><p v-if="charge.refundReview" class="mt-1 text-xs text-amber-700">Devolução a conferir</p></td>
                            <td class="whitespace-nowrap p-4">{{ charge.dueDate }}</td><td class="whitespace-nowrap p-4 font-semibold">{{ money(charge.value) }}</td>
                            <td class="p-4"><span :class="charge.status === 'pago' ? 'badge-success' : charge.status === 'atrasado' ? 'badge-danger' : 'badge-warning'">{{ labels[charge.status] ?? charge.status }}</span></td>
                            <td class="p-4 text-xs text-[var(--wn-muted)]">{{ charge.sentAt || '—' }}</td>
                            <td class="p-4"><div v-if="['pendente', 'atrasado'].includes(charge.status)" class="flex justify-end gap-2"><button class="btn-secondary whitespace-nowrap" :disabled="loading" @click="prepare(charge)">Preparar mensagem</button><button class="btn-primary whitespace-nowrap" :disabled="loading" @click="payment = { ...charge, competence: charge.dueDate.slice(3) }">Registrar pagamento</button></div><p v-else class="text-right text-xs text-[var(--wn-muted)]">{{ charge.paidAt ? `Pago em ${charge.paidAt}` : '—' }}</p></td>
                        </tr></template>
                    </tbody>
                </table>
            </div>
            <div v-if="loading" class="admin-mobile-card text-sm text-[var(--wn-muted)]">Carregando financeiro…</div>
            <div v-else-if="error" class="admin-mobile-card text-sm text-rose-700">Listagem indisponível. Use Tentar novamente acima.</div>
            <div v-else-if="!charges.length" class="admin-mobile-card text-sm">Nenhuma cobrança neste filtro. Escolha outro mês ou situação.</div>
            <template v-else>
                <article v-for="charge in charges" :key="`mobile-${charge.id}`" class="admin-mobile-card">
                    <div class="flex items-center justify-between gap-3">
                        <RouterLink :to="`/alunos/${charge.studentId}?tab=financial`" class="font-semibold">{{ charge.student }}</RouterLink>
                        <span :class="charge.status === 'pago' ? 'badge-success' : charge.status === 'atrasado' ? 'badge-danger' : 'badge-warning'">{{ labels[charge.status] ?? charge.status }}</span>
                    </div>
                    <dl class="admin-mobile-details">
                        <div><dt>Origem</dt><dd>{{ charge.plan }}<span v-if="charge.refundReview" class="block text-amber-700">Devolução a conferir</span></dd></div>
                        <div><dt>Valor</dt><dd class="font-semibold">{{ money(charge.value) }}</dd></div>
                        <div><dt>Vencimento</dt><dd>{{ charge.dueDate }}</dd></div>
                        <div><dt>Último envio</dt><dd>{{ charge.sentAt || '—' }}</dd></div>
                    </dl>
                    <div v-if="['pendente', 'atrasado'].includes(charge.status)" class="grid grid-cols-2 gap-2">
                        <button class="btn-secondary justify-center" :disabled="loading" @click="prepare(charge)">Preparar mensagem</button>
                        <button class="btn-primary justify-center" :disabled="loading" @click="payment = { ...charge, competence: charge.dueDate.slice(3) }">Registrar pagamento</button>
                    </div>
                    <p v-else class="text-xs text-[var(--wn-muted)]">{{ charge.paidAt ? `Pago em ${charge.paidAt}` : '—' }}</p>
                </article>
            </template>
            <div class="flex items-center justify-between gap-3 border-t border-[var(--wn-line)] p-4 text-sm"><span>{{ total }} itens · {{ money(listTotal) }} · página {{ page }} de {{ lastPage }}</span><div class="flex gap-2"><button class="btn-secondary" :disabled="loading || page <= 1" @click="load(page - 1)">Anterior</button><button class="btn-secondary" :disabled="loading || page >= lastPage" @click="load(page + 1)">Próxima</button></div></div>
        </section>

        <div class="finance-footnote"><details><summary>Como os indicadores são calculados</summary><p>Previsto = Recebido + Em aberto + Vencido. Recebido inclui todos os valores pagos no mês, entradas avulsas, recuperações e antecipações. Em aberto considera somente valores a vencer (incluindo hoje); Vencido considera os que passaram do vencimento. Mensalidades recorrentes são geradas automaticamente conforme o ciclo do plano. No gráfico, verde usa a data de recebimento; amarelo (a vencer) e vermelho (vencido) usam o vencimento.</p></details><RouterLink to="/configuracoes/mensagens">Modelos de mensagem</RouterLink></div>
        <ChargeActionDialog v-if="messageCharge || payment" :charge="messageCharge || payment" :action="payment ? 'payment' : 'message'" @close="messageCharge = null; payment = null" @updated="updated" />
        <GenerateChargeDialog v-if="createOpen" @close="createOpen = false" @created="created" />
    </section>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import ChargeActionDialog from '../components/ChargeActionDialog.vue';
import GenerateChargeDialog from '../components/GenerateChargeDialog.vue';
import FinanceOverview from '../components/FinanceOverview.vue';
import FinanceMovements from '../components/FinanceMovements.vue';
const movementRevision = ref(0);
const now = new Date();
const month = ref(now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'));
const search = ref(''), status = ref('todos'), charges = ref([]), summary = ref({}), dashboard = ref({});
const loading = ref(true), error = ref(''), notice = ref('');
const page = ref(1), lastPage = ref(1), total = ref(0), listTotal = ref(0), payment = ref(null), messageCharge = ref(null), createOpen = ref(false), chargeList = ref(null);
const labels = { pago: 'Paga', pendente: 'A vencer', atrasado: 'Vencida', cancelado: 'Cancelada' };
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
let requestId = 0;
async function load(target = 1) {
    if (!month.value) return;
    const id = ++requestId; loading.value = true; error.value = '';
    try {
        const { data } = await window.axios.get('/api/finance', { params: { month: month.value, q: search.value, status: status.value, page: target } });
        if (id !== requestId) return;
        listTotal.value = data.listTotal ?? 0; summary.value = data.summary; dashboard.value = data.dashboard ?? {}; charges.value = data.charges;
        page.value = data.pagination.page; lastPage.value = data.pagination.lastPage; total.value = data.pagination.total;
    } catch { if (id === requestId) { error.value = 'Não foi possível carregar o financeiro.'; charges.value = []; summary.value = {}; dashboard.value = {}; total.value = 0; listTotal.value = 0; page.value = 1; lastPage.value = 1; } }
    finally { if (id === requestId) loading.value = false; }
}
async function filterCharges(value) { status.value = value; search.value = ''; await load(1); await nextTick(); chargeList.value?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }); chargeList.value?.querySelector('h2')?.focus({ preventScroll: true }); }
function incomeCreated(value) { month.value = value; notice.value = 'Entrada registrada.'; movementRevision.value++; load(1); }
function setMonth(value) { if (value) { month.value = value; load(1); } }
function prepare(charge) { messageCharge.value = { ...charge, competence: charge.dueDate.slice(3) }; }
function updated(message) { movementRevision.value++; if (message) notice.value = message; load(page.value); }
async function created(charge) { movementRevision.value++; createOpen.value = false; notice.value = 'Cobrança disponível. Se já existia para esta competência, ela foi mantida sem duplicação.'; const parts = charge.dueDate?.split('/'); if (parts?.length === 3) month.value = parts[2] + '-' + parts[1]; search.value = ''; status.value = 'todos'; await load(1); }
onMounted(() => load());
</script>

