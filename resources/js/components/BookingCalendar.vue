<template>
    <div class="mt-3 rounded-xl border border-[var(--wn-line)] p-2 sm:p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <button type="button" class="booking-month-arrow" aria-label="Mês anterior" :disabled="disabled || month <= today.slice(0, 7)" @click="changeMonth(-1)">
                <ChevronLeft class="h-4 w-4" aria-hidden="true" />
            </button>
            <p class="text-sm font-semibold first-letter:uppercase" aria-live="polite">{{ monthLabel }}</p>
            <button type="button" class="booking-month-arrow" aria-label="Próximo mês" :disabled="disabled || month >= maxDate.slice(0, 7)" @click="changeMonth(1)">
                <ChevronRight class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
        <table class="w-full table-fixed text-center" :aria-label="monthLabel">
            <thead>
                <tr>
                    <th v-for="[short, full] in weekdays" :key="short" scope="col" class="pb-1 text-xs font-medium text-[var(--wn-muted)]">
                        <span aria-hidden="true">{{ short }}</span><span class="sr-only">{{ full }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(week, index) in calendarWeeks" :key="index">
                    <td v-for="(day, column) in week" :key="column" class="p-0.5">
                        <button v-if="day" type="button" class="booking-day"
                            :disabled="disabled || !datesWithSpots.has(day)"
                            :class="{ 'booking-day-available': datesWithSpots.has(day) }"
                            :aria-current="day === today ? 'date' : undefined"
                            :aria-pressed="datesWithSpots.has(day) ? date === day : undefined"
                            :aria-label="`${formatDay(day)} · ${datesWithSpots.has(day) ? 'com vagas' : 'indisponível'}`"
                            @click="emit('select-date', day)">
                            {{ Number(day.slice(8)) }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-[var(--wn-muted)]">
            <span class="inline-flex items-center gap-1.5"><span class="booking-key booking-key-available" aria-hidden="true"></span>Com vagas</span>
            <span class="inline-flex items-center gap-1.5"><span class="booking-key booking-key-unavailable" aria-hidden="true"></span>Indisponível</span>
        </div>
    </div>
    <p v-if="!monthHasAvailability" class="mt-3 text-sm text-[var(--wn-muted)]" role="status">
        {{ availableDates.length ? 'Nenhuma data com vagas neste mês.' : 'Nenhuma data com vagas nos próximos 60 dias.' }}
    </p>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    today: { type: String, required: true },
    maxDate: { type: String, required: true },
    month: { type: String, required: true },
    date: { type: String, required: true },
    availableDates: { type: Array, required: true },
    disabled: Boolean,
});
const emit = defineEmits(['change-month', 'select-date']);

const dayFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full', timeZone: 'UTC' });
const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const weekdays = [
    ['Dom', 'Domingo'], ['Seg', 'Segunda-feira'], ['Ter', 'Terça-feira'], ['Qua', 'Quarta-feira'],
    ['Qui', 'Quinta-feira'], ['Sex', 'Sexta-feira'], ['Sáb', 'Sábado'],
];
const datesWithSpots = computed(() => new Set(props.availableDates));
const monthHasAvailability = computed(() => props.availableDates.some((day) => day.startsWith(props.month)));
const monthStart = computed(() => new Date(`${props.month}-01T00:00:00Z`));
const monthLabel = computed(() => monthFormatter.format(monthStart.value));
const calendarWeeks = computed(() => {
    const first = monthStart.value;
    const dayCount = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
    const days = Array(first.getUTCDay()).fill(null);
    for (let day = 1; day <= dayCount; day++) {
        days.push(`${props.month}-${String(day).padStart(2, '0')}`);
    }
    while (days.length % 7) days.push(null);

    const weeks = [];
    for (let index = 0; index < days.length; index += 7) {
        weeks.push(days.slice(index, index + 7));
    }
    return weeks;
});

function formatDay(day) {
    return dayFormatter.format(new Date(day));
}

function changeMonth(amount) {
    const first = new Date(monthStart.value);
    first.setUTCMonth(first.getUTCMonth() + amount);
    emit('change-month', first.toISOString().slice(0, 7));
}
</script>

<style scoped>
.booking-month-arrow,
.booking-day {
    display: grid;
    place-items: center;
    border: 1px solid var(--wn-line);
    border-radius: 0.5rem;
}
.booking-month-arrow {
    width: 2.25rem;
    height: 2.25rem;
}
.booking-day {
    width: 100%;
    min-height: 2.5rem;
    font-size: 0.875rem;
}
.booking-day,
.booking-key-unavailable {
    background: var(--wn-canvas);
    color: var(--wn-muted);
}
.booking-month-arrow:disabled { opacity: 0.4; }
.booking-day-available,
.booking-key-available {
    background: var(--wn-primary-soft);
    border-color: var(--wn-primary-strong);
    color: var(--wn-primary-strong);
    font-weight: 700;
}
.booking-day[aria-pressed="true"] {
    background: var(--wn-primary-strong);
    color: white;
}
.booking-day[aria-current="date"] { text-decoration: underline; }
.booking-month-arrow:focus-visible,
.booking-day:focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: 2px;
}
.booking-key {
    width: 0.875rem;
    height: 0.875rem;
    border: 1px solid var(--wn-line);
    border-radius: 0.25rem;
}
</style>
