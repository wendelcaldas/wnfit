<template>
    <article class="panel-card dashboard-calendar" aria-label="Calendário de aulas">
        <div class="flex items-center justify-between gap-2">
            <button type="button" class="calendar-arrow" aria-label="Mês anterior" @click="changeMonth(-1)"><ChevronLeft class="h-4 w-4" /></button>
            <h3 class="text-center text-sm font-semibold capitalize" aria-live="polite">{{ monthLabel }}</h3>
            <button type="button" class="calendar-arrow" aria-label="Próximo mês" @click="changeMonth(1)"><ChevronRight class="h-4 w-4" /></button>
        </div>
        <div :aria-busy="loading" class="mt-2">
            <div class="grid grid-cols-7 text-center text-xs text-[var(--wn-muted)]" aria-hidden="true"><span v-for="day in weekdays" :key="day" class="py-2">{{ day }}</span></div>
            <div class="calendar-dates grid grid-cols-7 gap-x-1">
                <div v-for="(day, index) in days" :key="index" class="min-w-0">
                    <template v-if="day">
                        <button v-if="eventsFor(day.key).length" type="button" class="calendar-day calendar-day-action" :class="{ 'calendar-today': day.key === today, 'calendar-selected': day.key === (selected || lastSelected) }" :disabled="loading || !!error" :aria-current="day.key === today ? 'date' : undefined" :aria-label="`${longDate(day.key)}: ${eventsFor(day.key).length} compromisso(s)`" aria-haspopup="dialog" @click="openDay(day.key)">
                            {{ day.number }}<span class="calendar-dot" aria-hidden="true"></span>
                        </button>
                        <span v-else class="calendar-day" :class="{ 'calendar-today': day.key === today }" :aria-current="day.key === today ? 'date' : undefined" :aria-label="`${longDate(day.key)}: sem compromissos`">{{ day.number }}</span>
                    </template>
                </div>
            </div>
        </div>
        <p v-if="loading" role="status" class="sr-only">Carregando compromissos...</p>
        <div v-else-if="error" role="alert" class="mt-3 text-sm"><p>{{ error }}</p><button type="button" class="mt-2 underline" @click="loadMonth">Tentar novamente</button></div>
        <RouterLink to="/agenda" class="btn-primary calendar-agenda-link">Ir para a agenda <ChevronRight class="h-4 w-4" aria-hidden="true" /></RouterLink>
        <Teleport to="body">
            <dialog ref="dialog" class="calendar-dialog" aria-labelledby="calendar-day-title" aria-describedby="calendar-day-date" @click="backdropClick" @close="selected = null">
                <header class="day-agenda-header">
                    <div class="min-w-0"><p class="day-agenda-eyebrow">Agenda do dia · {{ selectedEvents.length }} compromissos</p><h2 id="calendar-day-title">Compromissos do dia</h2><p id="calendar-day-date">{{ selected ? longDate(selected) : '' }}</p></div>
                    <button type="button" class="day-agenda-close" aria-label="Fechar compromissos" autofocus @click="dialog.close()"><X class="h-5 w-5" /></button>
                </header>
                <div class="day-agenda-body" tabindex="0" aria-label="Compromissos em ordem de horário">
                    <ol class="day-agenda-timeline">
                        <li v-for="event in selectedEvents" :key="event.id" class="day-agenda-entry">
                            <div class="day-agenda-time"><strong>{{ event.date === selected ? event.time : 'Em curso' }}</strong><span>{{ event.endDate === selected ? event.endTime : `${shortDate(event.endDate)} ${event.endTime}` }}</span></div>
                            <article class="day-agenda-card">
                                <p class="day-agenda-modality">{{ modalityLabel(event.modality) }}</p>
                                <h3>{{ event.title }}</h3>
                                <div class="day-agenda-meta"><p><UserRound aria-hidden="true" />{{ event.instructor }}</p><p><MapPin aria-hidden="true" />{{ event.location }}</p></div>
                                <p class="day-agenda-occupancy"><Users aria-hidden="true" /><span><strong>{{ event.participants }}{{ event.capacity ? ` / ${event.capacity}` : '' }}</strong> {{ event.capacity ? 'vagas ocupadas' : 'alunos confirmados' }}</span></p>
                            </article>
                        </li>
                    </ol>
                </div>
                <footer class="day-agenda-footer"><RouterLink to="/agenda" @click="dialog.close()">Abrir agenda completa <ChevronRight class="h-4 w-4" aria-hidden="true" /></RouterLink></footer>
            </dialog>
        </Teleport>
    </article>
</template>

<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ChevronLeft, ChevronRight, MapPin, UserRound, Users, X } from 'lucide-vue-next';

const today = ref('');
const month = ref('');
const events = ref([]);
const loading = ref(true);
const error = ref('');
const selected = ref(null);
const lastSelected = ref(null);
const dialog = ref(null);
const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
let requestId = 0;
let controller;
const monthDate = computed(() => month.value ? new Date(`${month.value}-01T12:00:00`) : new Date());
const monthLabel = computed(() => new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(monthDate.value));
const days = computed(() => {
    if (!month.value) return [];
    const first = monthDate.value;
    const count = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    return [...Array(first.getDay()).fill(null), ...Array.from({ length: count }, (_, i) => ({ number: i + 1, key: `${month.value}-${String(i + 1).padStart(2, '0')}` }))];
});
const selectedEvents = computed(() => selected.value ? eventsFor(selected.value) : []);
function longDate(key) { return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full' }).format(new Date(`${key}T12:00:00`)); }
function shortDate(key) { return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(new Date(`${key}T12:00:00`)); }
function modalityLabel(value) { return ({ presencial: 'Presencial', online: 'Online', externo: 'Externo' })[value] || 'Aula'; }
function eventsFor(key) { return events.value.filter(event => event.date <= key && event.endDate >= key); }
async function openDay(key) {
    if (loading.value || error.value || !eventsFor(key).length) return;
    selected.value = key; lastSelected.value = key;
    await nextTick(); dialog.value.showModal();
}
function backdropClick(event) {
    if (event.target !== dialog.value) return;
    const bounds = dialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.value.close();
}
function changeMonth(amount) {
    if (!month.value) return;
    const date = monthDate.value;
    date.setMonth(date.getMonth() + amount);
    month.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    loadMonth();
}
async function loadMonth() {
    const id = ++requestId;
    controller?.abort();
    controller = new AbortController();
    loading.value = true; error.value = '';
    try {
        const { data } = await window.axios.get('/api/dashboard/calendar', { params: { month: month.value || undefined }, signal: controller.signal });
        if (id !== requestId) return;
        month.value = data.month; today.value = data.today; events.value = data.events;
        if (!lastSelected.value) lastSelected.value = data.today;
    } catch (failure) {
        if (id === requestId && failure.code !== 'ERR_CANCELED') error.value = 'Não foi possível carregar os compromissos.';
    } finally { if (id === requestId) loading.value = false; }
}
onMounted(loadMonth);
onBeforeUnmount(() => { requestId++; controller?.abort(); dialog.value?.close(); });
</script>

<style scoped>
.dashboard-calendar { display: flex; flex-direction: column; min-width: 0; width: 100%; padding: 1rem; }
.dashboard-calendar > [aria-busy] { margin-bottom: 1rem; }
.calendar-dates { grid-auto-rows: 2.2rem; min-height: 13.2rem; }
.calendar-agenda-link { display: flex; width: 100%; justify-content: center; align-items: center; gap: .5rem; margin-top: auto; }
.calendar-day { position: relative; display: grid; place-items: center; width: 2rem; max-width: 100%; height: 2rem; margin: .1rem auto; border-radius: 50%; font-size: .8rem; color: var(--wn-ink); }
.calendar-day-action { cursor: pointer; }
.calendar-day-action:hover { background: var(--wn-surface-soft); }
.calendar-day:focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: 1px; }
.calendar-day:disabled { opacity: .5; }
.calendar-today { font-weight: 700; box-shadow: inset 0 0 0 1px var(--wn-primary-strong); }
.calendar-selected, .calendar-selected:hover { background: var(--wn-primary-strong); color: white; font-weight: 700; }
.calendar-arrow { display: grid; place-items: center; width: 1.9rem; height: 1.9rem; border: 1px solid var(--wn-line); border-radius: 50%; color: var(--wn-primary-strong); }
.calendar-arrow:hover { background: var(--wn-primary-soft); }
.calendar-arrow:focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: 2px; }
.calendar-dot { position: absolute; bottom: .15rem; width: .25rem; height: .25rem; border-radius: 50%; background: var(--wn-primary-strong); }
.calendar-selected .calendar-dot { background: white; }
.calendar-dialog { --wn-ink: #151715; --wn-muted: #62685f; --wn-line: #e3e7df; --wn-primary-strong: #365314; box-sizing: border-box; margin: auto; padding: 0; border: 1px solid var(--wn-line); border-radius: 1rem; color: var(--wn-ink); background: white; width: min(calc(100% - 2rem), 40rem); max-width: calc(100% - 2rem); max-height: calc(100dvh - 2rem); overflow: hidden; box-shadow: 0 24px 80px #15201526; }
.calendar-dialog[open] { display: flex; flex-direction: column; }
.calendar-dialog::backdrop { background: rgba(15, 23, 42, .5); }
.day-agenda-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--wn-line); flex-shrink: 0; }
.day-agenda-eyebrow { margin-bottom: .4rem; font-size: .7rem; font-weight: 600; color: var(--wn-primary-strong); }
.day-agenda-header h2 { font-size: 1.2rem; font-weight: 700; line-height: 1.3; }
#calendar-day-date { margin-top: .35rem; font-size: .8rem; color: var(--wn-muted); overflow-wrap: anywhere; }
.day-agenda-close { display: grid; place-items: center; flex-shrink: 0; width: 2.5rem; height: 2.5rem; border: 1px solid var(--wn-line); border-radius: 50%; cursor: pointer; }
.day-agenda-close:hover { background: #f5f6f4; }
.day-agenda-body { min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 1.25rem 1.5rem; overscroll-behavior: contain; scrollbar-gutter: stable; }
.day-agenda-timeline { list-style: none; margin: 0; padding: 0; }
.day-agenda-entry { display: grid; grid-template-columns: 3.5rem minmax(0, 1fr); gap: 1rem; position: relative; padding-bottom: 1rem; }
.day-agenda-entry:last-child { padding-bottom: 0; }
.day-agenda-time { display: flex; flex-direction: column; gap: .25rem; padding-top: .85rem; font-size: .7rem; color: var(--wn-muted); overflow-wrap: anywhere; }
.day-agenda-time strong { font-size: .9rem; color: var(--wn-ink); font-variant-numeric: tabular-nums; }
.day-agenda-card { position: relative; min-width: 0; border: 1px solid var(--wn-line); border-radius: .8rem; padding: .85rem 1rem; background: #fafbf9; overflow-wrap: anywhere; }
.day-agenda-card::before { content: ''; position: absolute; top: 1.1rem; left: -.3rem; width: .5rem; height: .5rem; border-radius: 50%; background: #6a9435; box-shadow: 0 0 0 3px white; }
.day-agenda-modality { color: var(--wn-primary-strong); font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.day-agenda-card h3 { margin-top: .25rem; font-size: .95rem; font-weight: 650; line-height: 1.4; }
.day-agenda-meta { display: flex; flex-wrap: wrap; gap: .3rem 1rem; margin-top: .5rem; color: var(--wn-muted); font-size: .75rem; line-height: 1.4; }
.day-agenda-meta p, .day-agenda-occupancy { display: flex; align-items: flex-start; gap: .4rem; min-width: 0; }
.day-agenda-meta svg, .day-agenda-occupancy svg { width: .9rem; height: .9rem; flex-shrink: 0; margin-top: .1rem; }
.day-agenda-occupancy { margin-top: .5rem; padding-top: .4rem; border-top: 1px solid var(--wn-line); font-size: .7rem; line-height: 1.4; color: var(--wn-muted); }
.day-agenda-occupancy strong { color: var(--wn-ink); font-weight: 600; }
.day-agenda-footer { flex-shrink: 0; padding: .85rem 1.5rem; border-top: 1px solid var(--wn-line); }
.day-agenda-footer a { display: flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.5rem; font-size: .8rem; font-weight: 600; color: var(--wn-primary-strong); }
.calendar-dialog :is(button, a, [tabindex]):focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: -2px; }
@media (max-width: 479px) { .day-agenda-header { padding: 1rem; gap: .5rem; } .day-agenda-body { padding: 1rem .75rem; } .day-agenda-entry { grid-template-columns: 2.9rem minmax(0, 1fr); gap: .5rem; } .day-agenda-card { padding: .75rem; } .day-agenda-footer { padding: .6rem 1rem; } .day-agenda-header h2 { font-size: 1.05rem; } }
</style>
