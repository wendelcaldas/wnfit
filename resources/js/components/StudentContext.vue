<template>
    <aside class="student-context">
        <section class="panel-card student-identity">
            <div class="student-identity-heading">
                <span class="student-avatar">{{ student.initials }}</span>
                <div><h2>{{ student.name }}</h2><span class="badge-success">{{ student.status }}</span></div>
            </div>
            <p v-if="student.plan" class="student-plan">{{ student.plan }}</p>
            <p v-if="student.teacher" class="text-sm text-[var(--wn-muted)]">Professor: {{ student.teacher }}</p>
            <button class="student-edit" @click="$emit('edit')">Editar ficha <span aria-hidden="true">↗</span></button>
            <nav class="student-context-menu" aria-label="Seções do aluno">
                <button v-for="tab in tabs" :key="tab.key" :class="{ selected: activeTab === tab.key }" :aria-pressed="activeTab === tab.key" @click="$emit('select', tab.key)">{{ tab.label }}</button>
            </nav>
        </section>
        <details class="panel-card student-context-widget" :open="wide">
            <summary>Últimos 7 dias <span class="text-xs text-[var(--wn-muted)]">{{ trainedDays }} dias com treino</span></summary>
            <div class="student-week">
                <div v-for="day in student.recentActivity" :key="day.date" :title="`${dateLabel(day.date)}: ${day.completedSessions ? 'Treinou' : 'Sem registro'}`">
                    <span>{{ weekday(day.date) }}</span>
                    <span class="student-day" :class="{ trained: day.completedSessions > 0 }" :aria-label="`${dateLabel(day.date)}: ${day.completedSessions ? 'Treinou' : 'Sem registro'}`">{{ day.completedSessions ? '✓' : '–' }}</span>
                    <small>{{ Number(day.date.slice(-2)) }}</small>
                </div>
            </div>
            <p class="student-widget-caption">Verde: treino concluído · Cinza: sem registro</p>
        </details>
        <details class="panel-card student-context-widget" :open="wide">
            <summary>Financeiro <button class="student-detail-link" @click.prevent="$emit('select', 'financial')">Ver detalhes</button></summary>
            <div class="student-months">
                <div v-for="month in months" :key="month.key" class="student-month">
                    <div><strong>{{ month.label }}</strong><span>{{ month.period }}</span></div>
                    <div class="student-month-value"><strong>{{ month.charges.length ? money(month.value) : '—' }}</strong><span :class="month.tone">{{ month.status }}</span></div>
                </div>
            </div>
            <p class="student-widget-caption">Valores de cobranças já geradas. Sem cobrança não significa pagamento confirmado.</p>
        </details>
    </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
const props = defineProps({ student: Object, activeTab: String, tabs: Array });
defineEmits(['select', 'edit']);
const wide = ref(false);
let media;
const updateWidth = () => { wide.value = media.matches; };
onMounted(() => { media = window.matchMedia('(min-width: 1100px)'); updateWidth(); media.addEventListener('change', updateWidth); });
onUnmounted(() => media?.removeEventListener('change', updateWidth));
const money = value => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const weekday = date => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
const dateLabel = date => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR');
const trainedDays = computed(() => props.student.recentActivity?.filter(day => day.completedSessions > 0).length ?? 0);
const months = computed(() => {
    const current = props.student.recentActivity?.at(-1)?.date;
    const today = current ? new Date(`${current}T12:00:00`) : new Date();
    return [-1, 0, 1].map((offset, index) => {
        const date = new Date(today.getFullYear(), today.getMonth() + offset, 1);
        const key = `${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
        const charges = props.student.financial.charges.filter(charge => charge.competence === key);
        const payable = charges.filter(charge => charge.status !== 'cancelado');
        const late = payable.some(charge => charge.status === 'atrasado');
        const pending = payable.some(charge => charge.status === 'pendente');
        return { key, label: ['Mês anterior', 'Mês atual', 'Próximo mês'][index], period: date.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' }), charges, value: payable.reduce((sum, charge) => sum + charge.value, 0), status: !charges.length ? 'Sem cobrança' : !payable.length ? 'Cancelado' : late ? 'Em atraso' : pending ? 'Em aberto' : 'Pago', tone: late ? 'text-red-700' : pending ? 'text-amber-700' : payable.length ? 'text-[var(--wn-primary-strong)]' : '' };
    });
});
</script>
