<template>
    <section>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <label class="space-y-1"><span class="block text-sm font-semibold">Mês de referência</span><input v-model="month" type="month" required class="form-control" @change="load(1)" /></label>
            <RouterLink to="/configuracoes/mensagens" class="btn-secondary gap-2"><MessageSquareText class="h-4 w-4" />Modelos de mensagem</RouterLink>
        </div>
        <p v-if="error" role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-rose-700">{{ error }} <button class="underline" @click="load(page)">Tentar novamente</button></p>
        <p v-if="notice" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ notice }}</p>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" :aria-busy="loading">
            <article v-for="card in cards" :key="card.label" class="stat-card">
                <div class="flex items-center justify-between gap-2"><p class="text-sm text-[var(--wn-muted)]">{{ card.label }}</p><component :is="card.icon" class="h-5 w-5" :class="card.color" /></div>
                <p class="mt-3 text-2xl font-bold" :class="card.color">{{ loading ? '…' : money(card.value) }}</p>
                <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ card.caption }}</p>
            </article>
        </section>
        <p class="mt-3 text-xs leading-5 text-[var(--wn-muted)]">Previsto considera cobranças do mês e mensalidades estimadas de assinaturas ativas. Inclui {{ money(summary.projected) }} ainda sem cobrança gerada. Recebido considera a data do pagamento, inclusive de cobranças de outros meses. Vencido faz parte do valor em aberto.</p>

        <section class="panel-card mt-5">
            <h2 class="font-semibold">De onde vieram os recebimentos?</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div><p class="text-sm text-[var(--wn-muted)]">Mensalidades do próprio mês</p><strong>{{ money(summary.current) }}</strong></div>
                <div><p class="text-sm text-[var(--wn-muted)]">Recuperado de meses anteriores</p><strong>{{ money(summary.recovered) }}</strong></div>
                <div><p class="text-sm text-[var(--wn-muted)]">Antecipações de meses futuros</p><strong>{{ money(summary.advance) }}</strong></div>
            </div>
        </section>
        <section class="panel-card mt-6 !p-0">
            <div class="border-b border-[var(--wn-line)] p-5">
                <h2 class="text-lg font-semibold">Cobranças do mês</h2>
                <p class="mt-1 text-sm text-[var(--wn-muted)]">Filtradas pela data de vencimento. Selecione outro mês para consultar cobranças anteriores.</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_220px_auto]" @submit.prevent="load(1)">
                    <label><span class="mb-1 block text-xs font-semibold">Aluno</span><input v-model="search" class="form-control" placeholder="Buscar pelo nome" /></label>
                    <label><span class="mb-1 block text-xs font-semibold">Situação</span><select v-model="status" class="form-control" @change="load(1)"><option value="todos">Todas</option><option value="aberto">Em aberto</option><option value="atrasado">Vencidas</option><option value="hoje">Vencem hoje</option><option value="proximos">Próximos 7 dias</option><option value="pendente">A vencer</option><option value="pago">Pagas</option></select></label>
                    <button class="btn-secondary self-end justify-center" :disabled="loading">Buscar</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[var(--wn-surface-soft)] text-[var(--wn-muted)]"><tr><th class="p-4">Aluno / plano</th><th class="p-4">Vencimento</th><th class="p-4">Valor</th><th class="p-4">Situação</th><th class="p-4">Último envio registrado</th><th class="p-4 text-right">Ações</th></tr></thead>
                    <tbody class="divide-y divide-[var(--wn-line)]">
                        <tr v-if="loading"><td colspan="6" class="p-10 text-center text-[var(--wn-muted)]">Carregando financeiro…</td></tr>
                        <tr v-else-if="!charges.length"><td colspan="6" class="p-10 text-center"><p class="font-semibold">Nenhuma cobrança neste filtro</p><p class="mt-2 text-[var(--wn-muted)]">Escolha outro mês ou situação. Você também pode gerar uma cobrança no financeiro do aluno.</p></td></tr>
                        <template v-else><tr v-for="charge in charges" :key="charge.id">
                            <td class="p-4"><RouterLink :to="`/alunos/${charge.studentId}?tab=financial`" class="font-semibold text-[var(--wn-primary-strong)]">{{ charge.student }}</RouterLink><p class="mt-1 text-xs text-[var(--wn-muted)]">{{ charge.plan }}</p></td>
                            <td class="whitespace-nowrap p-4">{{ charge.dueDate }}</td><td class="whitespace-nowrap p-4 font-semibold">{{ money(charge.value) }}</td>
                            <td class="p-4"><span :class="charge.status === 'pago' ? 'badge-success' : charge.status === 'atrasado' ? 'badge-danger' : 'badge-warning'">{{ labels[charge.status] ?? charge.status }}</span></td>
                            <td class="p-4 text-xs text-[var(--wn-muted)]">{{ charge.sentAt || 'Nenhum envio confirmado' }}</td>
                            <td class="p-4"><div v-if="['pendente', 'atrasado'].includes(charge.status)" class="flex justify-end gap-2"><button class="btn-secondary whitespace-nowrap" :disabled="busy" @click="prepare(charge)">Preparar mensagem</button><button class="btn-primary whitespace-nowrap" :disabled="busy" @click="payment = charge">Dar baixa</button></div><p v-else class="text-right text-xs text-[var(--wn-muted)]">{{ charge.paidAt ? `Pago em ${charge.paidAt}` : '—' }}</p></td>
                        </tr></template>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-[var(--wn-line)] p-4 text-sm"><span>{{ total }} cobranças · página {{ page }} de {{ lastPage }}</span><div class="flex gap-2"><button class="btn-secondary" :disabled="loading || page <= 1" @click="load(page - 1)">Anterior</button><button class="btn-secondary" :disabled="loading || page >= lastPage" @click="load(page + 1)">Próxima</button></div></div>
        </section>

        <div v-if="draft || payment" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @keydown.esc="closeDialog">
            <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="finance-dialog-title" class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl" @keydown.tab="trapFocus">
                <div class="flex items-start justify-between gap-4"><h2 id="finance-dialog-title" class="text-xl font-bold">{{ draft ? 'Revisar mensagem de cobrança' : 'Confirmar pagamento' }}</h2><button aria-label="Fechar" class="icon-button" :disabled="busy" @click="closeDialog"><X class="h-5 w-5" /></button></div>
                <p v-if="dialogError" role="alert" class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ dialogError }}</p>
                <template v-if="draft">
                    <p class="mt-3 text-sm text-[var(--wn-muted)]">{{ draft.student }} · {{ money(draft.value) }} · vencimento {{ draft.dueDate }}</p>
                    <label class="mt-5 block"><span class="mb-2 block text-sm font-semibold">Texto da mensagem</span><textarea ref="messageInput" v-model="content" maxlength="1000" class="form-control" style="min-height: 18rem" :disabled="busy || opened" /></label>
                    <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ opened ? 'Texto salvo no histórico. Confirme abaixo apenas depois de enviar.' : 'Revise antes de abrir ou copiar. O envio é feito por você no WhatsApp.' }}</p>
                    <div class="mt-5 flex flex-wrap gap-2"><button class="btn-secondary" :disabled="busy || !content.trim()" @click="copyMessage">Copiar texto</button><button class="btn-primary" :disabled="busy || !content.trim()" @click="openWhatsApp">Abrir WhatsApp</button></div>
                    <button v-if="opened" class="btn-secondary mt-4 w-full justify-center" :disabled="busy" @click="markSent">Já enviei: registrar no histórico</button>
                </template>
                <template v-else>
                    <p class="mt-5 text-[var(--wn-muted)]">Registrar o recebimento integral de <strong class="text-[var(--wn-ink)]">{{ money(payment.value) }}</strong> de <strong>{{ payment.student }}</strong>?</p>
                    <p class="mt-3 text-sm text-[var(--wn-muted)]">A baixa será registrada com a data de hoje e aparecerá também no perfil do aluno.</p>
                    <label class="mt-4 block text-sm">Forma de pagamento<select v-model="paymentMethod" class="form-control mt-2"><option>PIX</option><option>Crédito</option><option>Débito</option><option>Dinheiro</option><option>Transferência</option><option>Boleto</option></select></label>
                    <div class="mt-6 flex justify-end gap-3"><button class="btn-secondary" :disabled="busy" @click="closeDialog">Cancelar</button><button class="btn-primary" :disabled="busy" @click="pay">{{ busy ? 'Registrando…' : 'Confirmar recebimento' }}</button></div>
                </template>
            </section>
        </div>
        <ChargeActionDialog v-if="messageCharge" :charge="messageCharge" action="message" @close="messageCharge = null" @updated="load(page)" />
    </section>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import ChargeActionDialog from '../components/ChargeActionDialog.vue';
const messageCharge = ref(null);
import { Wallet, CircleCheck, Clock, TriangleAlert, MessageSquareText, X } from 'lucide-vue-next';


const now = new Date();
const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
const search = ref(''), status = ref('todos'), charges = ref([]), summary = ref({});
const loading = ref(false), busy = ref(false), error = ref(''), notice = ref(''), dialogError = ref('');
const page = ref(1), lastPage = ref(1), total = ref(0), draft = ref(null), payment = ref(null);
const content = ref(''), opened = ref(false), paymentMethod = ref('PIX'), messageInput = ref(null);
const dialog = ref(null);
let previousFocus;
watch(() => Boolean(draft.value || payment.value), async visible => {
    if (visible) {
        previousFocus = document.activeElement;
        await nextTick();
        dialog.value?.querySelector('textarea, select, button')?.focus();
    } else {
        previousFocus?.focus();
        dialogError.value = '';
        paymentMethod.value = 'PIX';
    }
});
function trapFocus(event) {
    const elements = [...dialog.value.querySelectorAll('button:not(:disabled), textarea:not(:disabled), select:not(:disabled)')];
    const first = elements[0], last = elements.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
const labels = { pago: 'Paga', pendente: 'A vencer', atrasado: 'Vencida', cancelado: 'Cancelada' };
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const cards = computed(() => [
    { label: 'Previsto no mês', value: summary.value.expected, caption: 'Cobranças + estimativa de mensalidades', icon: Wallet, color: 'text-[var(--wn-ink)]' },
    { label: 'Recebido no mês', value: summary.value.received, caption: 'Pagamentos registrados neste mês', icon: CircleCheck, color: 'text-emerald-700' },
    { label: 'Em aberto no mês', value: summary.value.open, caption: 'Cobranças do mês ainda não pagas', icon: Clock, color: 'text-amber-700' },
    { label: 'Vencido no mês', value: summary.value.overdue, caption: 'Parte do aberto que passou do prazo', icon: TriangleAlert, color: 'text-rose-700' },
]);
let requestId = 0;
async function load(target = 1) {
    if (!month.value) return;
    const id = ++requestId;
    loading.value = true; error.value = '';
    try {
        const { data } = await window.axios.get('/api/finance', { params: { month: month.value, q: search.value, status: status.value, page: target } });
        if (id !== requestId) return;
        summary.value = data.summary; charges.value = data.charges;
        page.value = data.pagination.page; lastPage.value = data.pagination.lastPage; total.value = data.pagination.total;
    } catch (e) { if (id === requestId) { error.value = 'Não foi possível carregar o financeiro.'; charges.value = []; summary.value = {}; total.value = 0; } }
    finally { if (id === requestId) loading.value = false; }
}
function closeDialog() { if (!busy.value) { draft.value = null; payment.value = null; dialogError.value = ''; } }
function prepare(charge) {
    messageCharge.value = { ...charge, competence: charge.dueDate.slice(3) };
}
async function saveDraft() {
    const { data } = await window.axios.patch(`/api/messages/${draft.value.message.id}`, { content: content.value });
    draft.value.message = data.message;
    return data.message;
}
async function openWhatsApp() {
    const popup = window.open('about:blank', '_blank');
    if (!popup) { dialogError.value = 'Permita a abertura de uma nova janela para acessar o WhatsApp.'; return; }
    popup.opener = null; busy.value = true; dialogError.value = '';
    try {
        const message = await saveDraft();
        await window.axios.post(`/api/messages/${message.id}/opened`);
        popup.location.href = message.manualUrl; opened.value = true;
    } catch (e) { popup.close(); dialogError.value = 'Não foi possível abrir a mensagem. Tente novamente.'; }
    finally { busy.value = false; }
}
async function copyMessage() {
    busy.value = true; dialogError.value = '';
    try { await navigator.clipboard.writeText(content.value); await saveDraft(); opened.value = true; }
    catch (e) { dialogError.value = 'Não foi possível copiar e salvar. Selecione o texto ou tente novamente.'; }
    finally { busy.value = false; }
}
async function markSent() {
    busy.value = true; dialogError.value = '';
    try { await window.axios.post(`/api/messages/${draft.value.message.id}/sent`); draft.value = null; notice.value = 'Envio manual registrado. A cobrança continua em aberto até o pagamento.'; await load(page.value); }
    catch (e) { dialogError.value = 'Não foi possível registrar o envio.'; }
    finally { busy.value = false; }
}
async function pay() {
    busy.value = true; dialogError.value = '';
    try { await window.axios.post(`/api/charges/${payment.value.id}/pay`, { method: paymentMethod.value }); payment.value = null; notice.value = 'Pagamento registrado com sucesso.'; await load(page.value); }
    catch (e) { dialogError.value = e.response?.data?.message || 'Não foi possível registrar o pagamento.'; }
    finally { busy.value = false; }
}
onMounted(() => load());
</script>



