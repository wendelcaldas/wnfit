<template>
    <aside class="panel-card finance-movements" aria-labelledby="movements-title">
        <div class="finance-panel-heading"><div><h2 id="movements-title">Entradas</h2><p>Recebimentos do mês</p></div><button class="finance-add-income" aria-label="Adicionar entrada avulsa" title="Adicionar entrada avulsa" @click="createOpen = true"><Plus :size="19" /></button></div>
        <label class="finance-movement-source mt-4">Fonte<select v-model="source" class="form-control"><option value="todos">Todas as fontes</option><option value="direto">Mensalidades e inscrições</option><option value="boleto">Boletos</option><option value="avulso">Entradas avulsas</option><option value="totalpass">TotalPass</option><option value="wellhub">Wellhub</option></select></label>
        <div class="finance-movement-total"><span>{{ loading || error ? '—' : total }} entradas</span><strong>{{ loading ? '…' : error ? '—' : money(listTotal) }}</strong></div>
        <div class="finance-movement-items" aria-live="polite" :aria-busy="loading">
            <p v-if="loading" class="finance-movement-empty">Carregando entradas…</p>
            <p v-else-if="error" class="finance-movement-empty" role="alert">{{ error }} <button class="underline" @click="load(page)">Tentar novamente</button></p>
            <p v-else-if="!items.length" class="finance-movement-empty">Nenhuma entrada recebida neste período e fonte.</p>
            <article v-for="item in loading || error ? [] : items" :key="item.id" class="finance-income-row">
                <span class="finance-income-icon" :class="'source-' + item.source" :title="sourceLabel(item)"><component :is="sourceIcon(item.source)" :size="18" aria-hidden="true" /></span>
                <div class="finance-income-copy"><RouterLink v-if="item.studentId" :to="`/alunos/${item.studentId}?tab=financial`">{{ item.title }}</RouterLink><strong v-else>{{ item.title }}</strong><span>{{ sourceLabel(item) }}<small v-if="item.demo"> · Demo</small></span></div>
                <div class="finance-income-amount"><strong>{{ money(item.value) }}</strong><span>{{ shortDate(item.date) }}</span></div>
            </article>
        </div>
        <div class="finance-movement-pagination"><button :disabled="loading || page <= 1" @click="load(page - 1)" aria-label="Entradas anteriores">Anterior</button><span>{{ page }} / {{ lastPage }}</span><button :disabled="loading || page >= lastPage" @click="load(page + 1)" aria-label="Próximas entradas">Próxima</button></div>
        <IncomeEntryDialog v-if="createOpen" @close="createOpen = false" @created="created" />
    </aside>
</template>
<script setup>
import { ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Plus, Barcode, HandCoins, ScanLine, TicketCheck, Wallet } from 'lucide-vue-next';
import IncomeEntryDialog from './IncomeEntryDialog.vue';
const props = defineProps({ month: { type: String, required: true }, revision: Number });
const emit = defineEmits(['updated']);
const source = ref('todos'), items = ref([]), loading = ref(false), error = ref(''), createOpen = ref(false);
const page = ref(1), lastPage = ref(1), total = ref(0), listTotal = ref(0);
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const sourceLabel = item => ({ wellhub: 'Check-in Wellhub · repasse', totalpass: 'Check-in TotalPass · repasse', boleto: 'Boleto pago', avulso: 'Entrada avulsa' }[item.source] || item.description + ' · ' + (item.method || 'Recebida'));
const sourceIcon = source => ({ wellhub: ScanLine, totalpass: TicketCheck, boleto: Barcode, avulso: HandCoins }[source] || Wallet);
const shortDate = value => value ? value.slice(8, 10) + '/' + value.slice(5, 7) : '—';
let requestId = 0;
async function load(target = 1) {
    const id = ++requestId; loading.value = true; error.value = '';
    try {
        const { data } = await window.axios.get('/api/finance/income', { params: { month: props.month, source: source.value, page: target } });
        if (id !== requestId) return;
        items.value = data.entries; total.value = data.pagination.total; listTotal.value = data.totalValue;
        page.value = data.pagination.page; lastPage.value = data.pagination.lastPage;
    } catch { if (id === requestId) { error.value = 'Não foi possível carregar.'; items.value = []; total.value = 0; } }
    finally { if (id === requestId) loading.value = false; }
}
function created(month) { createOpen.value = false; source.value = 'todos'; emit('updated', month); load(1); }
watch(() => [props.month, props.revision, source.value], () => load(1), { immediate: true });
</script>
