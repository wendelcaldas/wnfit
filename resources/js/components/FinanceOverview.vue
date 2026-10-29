<template>
    <div class="finance-overview">
        <div class="finance-summary-row">
            <div class="finance-metrics">
            <component :is="card.filter ? 'button' : 'article'" v-for="card in cards" :key="card.label" class="stat-card finance-metric" :class="card.tone" :title="card.caption" :disabled="card.filter ? loading || !!error : undefined" @click="card.filter && emit('filter', card.filter)">
                <component :is="card.icon" class="finance-metric-icon" :size="18" aria-hidden="true" />
                <div class="finance-metric-copy"><span>{{ card.label }}</span><strong>{{ loading ? '…' : error ? '—' : money(card.value) }}</strong></div>
            </component>
        </div>
            <div class="finance-period"><label for="finance-month">Período</label><div><button class="icon-button" aria-label="Mês anterior" :disabled="loading" @click="changeMonth(-1)"><ChevronLeft :size="18" /></button><input id="finance-month" :value="month" type="month" required class="form-control" @change="emit('month', $event.target.value)" /><button class="icon-button" aria-label="Próximo mês" :disabled="loading" @click="changeMonth(1)"><ChevronRight :size="18" /></button></div></div>
        </div>
        <div class="finance-charts">
            <section class="panel-card finance-cash" aria-labelledby="cash-title">
                <div class="finance-panel-heading"><div><h2 id="cash-title">Recebimentos do mês</h2><p>Recebimentos e valores a receber por dia</p></div><div class="finance-chart-keys"><span class="finance-chart-key"><i></i>Recebido</span><span class="finance-chart-key"><i style="background: #d79a27"></i>A vencer</span><span class="finance-chart-key"><i style="background: #d87979"></i>Vencido</span></div></div>
                <div class="finance-cash-total"><strong>{{ loading ? '…' : error ? '—' : money(summary.received) }}</strong><span>recebidos em {{ monthLabel }}</span></div>
                <div v-if="loading || error" class="finance-chart-placeholder" role="status">{{ loading ? 'Carregando recebimentos…' : 'Gráfico indisponível. Tente novamente.' }}</div>
                <div v-else class="finance-bar-chart finance-interactive-chart" @mouseleave="activeDay = null" @keydown.esc="activeDay = null">
                    <div class="finance-chart-totals"><span><i class="finance-dot-paid"></i>Recebido <strong>{{ money(summary.received) }}</strong></span><span><i class="finance-dot-upcoming"></i>A vencer <strong>{{ money(summary.open) }}</strong></span><span><i class="finance-dot-pending"></i>Vencido <strong>{{ money(summary.overdue) }}</strong></span></div>
                    <svg viewBox="0 0 640 280" role="group" aria-labelledby="finance-chart-title finance-chart-description">
                        <title id="finance-chart-title">Recebidos e valores a receber em {{ monthLabel }}</title><desc id="finance-chart-description">Verde acima de zero: entradas na data do recebimento. Amarelo-alaranjado abaixo: valores a vencer. Vermelho abaixo: valores vencidos. Ambos pela data do vencimento. Passe o mouse, toque ou use Tab nos dias para consultar os valores. As barras abaixo de zero representam valores a receber, não despesas.</desc>
                        <g v-for="tick in [-1, -.5, 0, .5, 1]" :key="tick"><line x1="62" x2="634" :y1="135 - tick * 100" :y2="135 - tick * 100" :stroke="tick ? 'var(--wn-line)' : '#a8b0a1'" :stroke-dasharray="tick ? '3 5' : undefined" /><text x="54" :y="139 - tick * 100" text-anchor="end" class="finance-axis">{{ compactMoney(Math.abs(tick) * chartMax) }}</text></g>
                        <g v-for="(day, index) in daily" :key="day.date" tabindex="0" role="button" :aria-label="dayLabel(day)" :aria-pressed="activeDay === index" class="finance-chart-day" @mouseenter="activeDay = index" @focus="activeDay = index" @blur="activeDay = null" @click="activeDay = index" @keydown.enter.prevent="activeDay = index" @keydown.space.prevent="activeDay = index">
                            <rect :x="64 + index * barStep" y="25" :width="barStep" height="220" rx="4" :fill="activeDay === index ? '#74865b12' : 'transparent'" />
                            <rect :x="66 + index * barStep" :y="135 - Number(day.paid) / chartMax * 100" :width="Math.max(4, barStep - 5)" :height="Number(day.paid) / chartMax * 100" rx="3" :fill="activeDay === index ? '#639424' : '#84b82b'" />
                            <rect :x="66 + index * barStep" y="135" :width="Math.max(4, barStep - 5)" :height="Number(day.overdue) / chartMax * 100" rx="3" :fill="activeDay === index ? '#be5555' : '#d87979'" />
                            <rect :x="66 + index * barStep" :y="135 + Number(day.overdue) / chartMax * 100" :width="Math.max(4, barStep - 5)" :height="Number(day.upcoming) / chartMax * 100" rx="3" :fill="activeDay === index ? '#b97c13' : '#d79a27'" />
                        </g>
                        <text v-for="day in axisDays" :key="day" :x="66 + (day - 1) * barStep + barStep / 2" y="267" text-anchor="middle" class="finance-axis">{{ String(day).padStart(2, '0') }}</text>
                    </svg>
                    <div v-if="hoveredDay" class="finance-chart-tooltip" :style="{ left: Math.min(65, Math.max(5, (66 + activeDay * barStep) / 640 * 100)) + '%' }" role="status">
                        <strong>{{ formatDate(hoveredDay.date) }}</strong>
                        <span><i class="finance-dot-paid"></i>Recebido <b>{{ money(hoveredDay.paid) }}</b></span>
                        <span><i class="finance-dot-upcoming"></i>A vencer <b>{{ money(hoveredDay.upcoming) }}</b></span>
                        <span><i class="finance-dot-pending"></i>Vencido <b>{{ money(hoveredDay.overdue) }}</b></span>
                        <small>{{ hoveredDay.paidCount }} entradas · {{ hoveredDay.pendingCount }} valores a receber</small>
                    </div>
                    <p v-if="!hasChartValues" class="finance-chart-empty">Nenhuma movimentação neste mês.</p>
                    <p class="finance-chart-help">Passe o mouse ou toque em um dia para ver os valores. Amarelo: a vencer · Vermelho: vencido.</p>
                </div>
                <div class="finance-source-grid"><div v-for="source in sources" :key="source.label"><span>{{ source.label }}</span><strong>{{ loading ? '…' : error ? '—' : money(source.value) }}</strong></div></div>
                <details v-if="!loading && !error" class="finance-chart-details"><summary>Ver valores por dia</summary><dl><div v-for="day in daily" :key="day.date"><dt>{{ day.date.split('-').reverse().join('/') }}</dt><dd>Recebido {{ money(day.paid) }} · A vencer {{ money(day.upcoming) }} · Vencido {{ money(day.overdue) }}</dd></div></dl></details>
            </section>
            <slot name="movements" />
        </div>
        <div class="finance-list-shortcuts" aria-label="Filtrar cobranças"><button v-for="tab in tabs" :key="tab.value" :class="{ active: status === tab.value }" :aria-pressed="status === tab.value" :disabled="loading || !!error" @click="emit('filter', tab.value)">{{ tab.label }}<span>{{ loading || error ? '—' : dashboard.counts?.[tab.count] ?? 0 }}</span></button></div>
    </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { Wallet, CircleCheck, Clock, TriangleAlert, ChevronLeft, ChevronRight } from 'lucide-vue-next';
const props = defineProps({ month: { type: String, required: true }, summary: { type: Object, required: true }, dashboard: { type: Object, required: true }, loading: Boolean, error: String, status: String });
const emit = defineEmits(['month', 'filter']);
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const compactMoney = value => new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const monthLabel = computed(() => { const [year, number] = props.month.split('-').map(Number); return new Date(year, number - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }); });
const cards = computed(() => [
    { label: 'Previsto', value: props.summary.expected, caption: 'Recebido + Em aberto (a vencer) + Vencido', icon: Wallet, tone: '', filter: 'previsto' },
    { label: 'Recebido', value: props.summary.received, caption: 'Entradas registradas no mês', icon: CircleCheck, tone: 'finance-positive', filter: 'pago' },
    { label: 'Em aberto', value: props.summary.open, caption: 'Somente valores a vencer, incluindo hoje; não inclui vencidos', icon: Clock, tone: 'finance-pending', filter: 'aberto' },
    { label: 'Vencido', value: props.summary.overdue, caption: 'Valor que precisa de atenção', icon: TriangleAlert, tone: 'finance-overdue', filter: 'atrasado' },
]);
const sources = computed(() => [{ label: 'Do próprio mês', value: props.summary.current }, { label: 'Meses anteriores', value: props.summary.recovered }, { label: 'Antecipações', value: props.summary.advance }, { label: 'Avulsas', value: props.summary.manual }]);
const daily = computed(() => props.dashboard.daily ?? []);
const chartMax = computed(() => { const max = Math.max(100, ...daily.value.map(day => Math.max(Number(day.paid), Number(day.pending)))); const step = 10 ** Math.floor(Math.log10(max)); return Math.ceil(max / step) * step; });
const barStep = computed(() => 564 / Math.max(1, daily.value.length));
const axisDays = computed(() => [...new Set([1, 5, 10, 15, 20, 25, daily.value.length])].filter(day => day > 0 && day <= daily.value.length));
const activeDay = ref(null);
const hoveredDay = computed(() => activeDay.value === null ? null : daily.value[activeDay.value]);
const formatDate = value => value.split('-').reverse().join('/');
const dayLabel = day => formatDate(day.date) + '. Recebido: ' + money(day.paid) + '. A vencer: ' + money(day.upcoming) + '. Vencido: ' + money(day.overdue);
const hasChartValues = computed(() => daily.value.some(day => Number(day.paid) || Number(day.pending)));
const tabs = [{ label: 'Todas', value: 'todos', count: 'all' }, { label: 'A vencer', value: 'aberto', count: 'open' }, { label: 'Vencidas', value: 'atrasado', count: 'overdue' }, { label: 'Vencem hoje', value: 'hoje', count: 'today' }, { label: 'Recebidas', value: 'pago', count: 'paid' }];
function changeMonth(step) { const [year, number] = props.month.split('-').map(Number); const date = new Date(year, number - 1 + step, 1); emit('month', date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0')); }
</script>
