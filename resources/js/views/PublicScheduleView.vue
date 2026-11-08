<template>
    <main class="min-h-screen bg-[var(--wn-canvas)] px-4 py-10 text-[var(--wn-ink)] sm:py-16">
        <div class="mx-auto max-w-xl">
            <p class="mb-5 text-center text-xl font-bold">WNFit</p>
            <section class="panel-card">
                <p v-if="info" class="text-sm font-semibold text-[var(--wn-primary-strong)]">{{ info.organization }}</p>
                <h1 v-if="!reservation" class="mt-2 text-2xl font-bold">Reserve sua vaga</h1>
                <p v-if="loading" class="mt-5 text-sm text-[var(--wn-muted)]" role="status">Carregando agenda...</p>
                <p v-else-if="unavailable" class="mt-5 text-sm">Este link de agenda está indisponível.</p>
                <ActionFeedback v-if="!info" :message="error" type="error" :action="() => load()" />

                <div v-if="reservation" class="mt-4" role="status">
                    <CheckCircle2 class="mb-3 h-9 w-9 text-green-600" aria-hidden="true" />
                    <h1 ref="confirmationHeading" tabindex="-1" class="text-2xl font-bold">Vaga confirmada</h1>
                    <p class="mt-4 font-semibold">{{ reservation.title }}</p>
                    <p class="mt-1">{{ formatDay(reservation.date) }} · {{ reservation.start }}–{{ reservation.end }}</p>
                    <p class="mt-3 text-sm">Referência: <strong>{{ reservation.reference }}</strong></p>
                    <p class="mt-2 text-sm text-[var(--wn-muted)]">Se precisar de ajuda com sua reserva, fale com {{ info.organization }}.</p>
                    <button type="button" class="btn-secondary mt-5" @click="chooseAnotherDate">Consultar outra data</button>
                </div>
                <form v-else-if="info" class="mt-6 space-y-5" @submit.prevent="book">
                    <fieldset class="grid gap-3 sm:grid-cols-2" :disabled="saving">
                        <legend class="mb-2 text-sm font-semibold text-[var(--wn-primary-strong)]">Seus dados</legend>
                        <label class="block text-sm font-semibold">Seu nome
                            <input ref="nameInput" v-model.trim="name" class="form-control mt-2" required maxlength="120" autocomplete="name" />
                        </label>
                        <label class="block text-sm font-semibold">Telefone com DDD
                            <input v-model.trim="phone" v-input-mask="'phone'" class="form-control mt-2" required type="tel" maxlength="30" autocomplete="tel" placeholder="(11) 99999-9999" />
                        </label>
                    </fieldset>
                    <fieldset :disabled="saving">
                        <legend ref="datesHeading" tabindex="-1" class="text-sm font-semibold">Escolha uma data</legend>
                        <div class="mt-3 rounded-xl border border-[var(--wn-line)] p-2 sm:p-3">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <button type="button" class="booking-month-arrow" aria-label="Mês anterior" :disabled="month <= info.today.slice(0, 7)" @click="changeMonth(-1)">
                                    <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <p class="text-sm font-semibold first-letter:uppercase" aria-live="polite">{{ monthLabel }}</p>
                                <button type="button" class="booking-month-arrow" aria-label="Próximo mês" :disabled="month >= info.maxDate.slice(0, 7)" @click="changeMonth(1)">
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
                                                :disabled="!availableDates.has(day)"
                                                :aria-current="day === info.today ? 'date' : undefined"
                                                :aria-pressed="availableDates.has(day) ? date === day : undefined"
                                                :aria-label="`${formatDay(day)} · ${availableDates.has(day) ? 'com vagas' : 'indisponível'}`"
                                                @click="selectDate(day)">
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
                            {{ info.availableDates.length ? 'Nenhuma data com vagas neste mês.' : 'Nenhuma data com vagas nos próximos 60 dias.' }}
                        </p>
                    </fieldset>
                    <ActionFeedback :message="availabilityError" type="error" :action="loadAvailability" />
                    <fieldset v-if="date && info.availableDates.includes(date)" :disabled="saving">
                        <legend class="text-sm font-semibold first-letter:uppercase">{{ formatDay(date) }} · Horários com vagas</legend>
                        <p v-if="checking" class="mt-3 text-sm text-[var(--wn-muted)]" role="status">Buscando horários...</p>
                        <div v-else-if="events.length" class="mt-3 grid gap-2">
                            <label v-for="event in events" :key="event.id" class="flex cursor-pointer flex-wrap items-center gap-3 rounded-xl border p-3"
                                :class="selectedId === event.id ? 'border-[var(--wn-primary-strong)] bg-[var(--wn-canvas)]' : 'border-[var(--wn-line)]'">
                                <input v-model="selectedId" type="radio" name="appointment" :value="event.id" />
                                <span class="min-w-0 flex-1">
                                    <strong class="block break-words text-sm">{{ event.title }}</strong>
                                    <span class="text-sm text-[var(--wn-muted)]">{{ event.start }}–{{ event.end }}</span>
                                </span>
                                <span class="rounded-full bg-[var(--wn-canvas)] px-2.5 py-1 text-xs font-semibold">
                                    <span aria-hidden="true">{{ event.availableSpots }}/{{ event.capacity }} livres</span>
                                    <span class="sr-only">{{ event.availableSpots }} de {{ event.capacity }} vagas disponíveis</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                    <p v-if="closedDate" class="text-sm text-[var(--wn-muted)]" role="status">{{ formatDay(closedDate) }} ficou sem vagas. Selecione outra data disponível.</p>
                    <ActionFeedback :message="error" type="error" />
                    <p class="text-xs text-[var(--wn-muted)]">Ao confirmar uma vaga, seus dados serão enviados à equipe para organizar a reserva.</p>
                    <button class="btn-primary w-full justify-center" :disabled="saving || checking || !selectedId" type="submit">
                        {{ saving ? 'Confirmando...' : 'Confirmar vaga' }}
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const route = useRoute();
const endpoint = computed(() => '/api/public/schedule/' + encodeURIComponent(route.params.slug));
const info = ref(null);
const loading = ref(true);
const unavailable = ref(false);
const error = ref('');
const availabilityError = ref('');
const name = ref('');
const phone = ref('');
const date = ref('');
const month = ref('');
const closedDate = ref('');
const events = ref([]);
const selectedId = ref(null);
const checking = ref(false);
const saving = ref(false);
const reservation = ref(null);
const nameInput = ref(null);
const datesHeading = ref(null);
const confirmationHeading = ref(null);
const requestIds = new Map();
const fullDateFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full', timeZone: 'UTC' });
const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const weekdays = [
    ['Dom', 'Domingo'], ['Seg', 'Segunda-feira'], ['Ter', 'Terça-feira'], ['Qua', 'Quarta-feira'],
    ['Qui', 'Quinta-feira'], ['Sex', 'Sexta-feira'], ['Sáb', 'Sábado'],
];
const availableDates = computed(() => new Set(info.value?.availableDates));
const monthHasAvailability = computed(() => info.value?.availableDates.some((day) => day.startsWith(month.value)));
const monthStart = computed(() => new Date(`${month.value}-01T00:00:00Z`));
const monthLabel = computed(() => monthFormatter.format(monthStart.value));
const calendarWeeks = computed(() => {
    const first = monthStart.value;
    const dayCount = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
    const days = Array(first.getUTCDay()).fill(null);
    for (let day = 1; day <= dayCount; day++) {
        days.push(`${month.value}-${String(day).padStart(2, '0')}`);
    }
    while (days.length % 7) days.push(null);

    const weeks = [];
    for (let index = 0; index < days.length; index += 7) {
        weeks.push(days.slice(index, index + 7));
    }
    return weeks;
});
let infoSequence = 0;
let availabilitySequence = 0;

function formatDay(value) {
    return fullDateFormatter.format(new Date(value));
}

function changeMonth(amount) {
    const first = new Date(monthStart.value);
    first.setUTCMonth(first.getUTCMonth() + amount);
    month.value = first.toISOString().slice(0, 7);
    ++availabilitySequence;
    date.value = '';
    closedDate.value = '';
    selectedId.value = null;
    events.value = [];
    error.value = '';
    availabilityError.value = '';
    checking.value = false;
}

function selectDate(day) {
    if (date.value === day) return;
    date.value = day;
    return loadAvailability();
}

function rateLimitMessage(response) {
    if (response?.status !== 429) return '';
    const seconds = Number(response.headers?.['retry-after']);
    if (!Number.isInteger(seconds) || seconds < 1) return 'Muitas tentativas. Aguarde um instante e tente novamente.';
    return `Muitas tentativas. Tente novamente em ${seconds} ${seconds === 1 ? 'segundo' : 'segundos'}.`;
}

async function load(resetIdentity = true) {
    const sequence = ++infoSequence;
    ++availabilitySequence;
    info.value = null;
    if (resetIdentity) {
        name.value = '';
        phone.value = '';
        requestIds.clear();
    }
    date.value = '';
    closedDate.value = '';
    selectedId.value = null;
    events.value = [];
    availabilityError.value = '';
    checking.value = false;
    reservation.value = null;
    saving.value = false;
    unavailable.value = false;
    error.value = '';
    loading.value = true;
    try {
        const { data } = await window.axios.get(endpoint.value);
        if (sequence === infoSequence) {
            month.value = data.today.slice(0, 7);
            info.value = data;
        }
    } catch (exception) {
        if (sequence !== infoSequence) return;
        if (exception.response?.status === 404) unavailable.value = true;
        else error.value = rateLimitMessage(exception.response) || 'Não foi possível carregar a agenda.';
    } finally {
        if (sequence === infoSequence) loading.value = false;
    }
}

async function loadAvailability() {
    const sequence = ++availabilitySequence;
    const selectedDate = date.value;
    selectedId.value = null;
    events.value = [];
    error.value = '';
    availabilityError.value = '';
    closedDate.value = '';
    checking.value = true;
    try {
        const { data } = await window.axios.get(endpoint.value + '/availability', { params: { date: selectedDate } });
        if (sequence !== availabilitySequence) return;
        events.value = data.events;
        if (!data.events.length) {
            info.value.availableDates = info.value.availableDates.filter((day) => day !== selectedDate);
            closedDate.value = selectedDate;
            date.value = '';
            await nextTick();
            datesHeading.value?.focus();
        }
    } catch (exception) {
        if (sequence === availabilitySequence) {
            availabilityError.value = rateLimitMessage(exception.response) || 'Não foi possível consultar os horários desta data.';
        }
    } finally {
        if (sequence === availabilitySequence) checking.value = false;
    }
}

async function book() {
    const event = events.value.find((item) => item.id === selectedId.value);
    if (!event || saving.value) return;
    if (!name.value) {
        nameInput.value?.focus();
        return;
    }
    const sequence = infoSequence;
    const requestKey = JSON.stringify([event.id, name.value, phone.value]);
    if (!requestIds.has(requestKey)) requestIds.set(requestKey, crypto.randomUUID());
    saving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post(endpoint.value + '/reservations', {
            eventId: event.id, date: event.date, start: event.start, end: event.end,
            name: name.value, phone: phone.value, requestId: requestIds.get(requestKey),
        });
        if (sequence !== infoSequence) return;
        reservation.value = data.reservation;
        await nextTick();
        confirmationHeading.value?.focus();
    } catch (exception) {
        if (sequence !== infoSequence) return;
        const response = exception.response;
        if (response?.status === 409) await loadAvailability();
        if (sequence !== infoSequence) return;
        error.value = rateLimitMessage(response)
            || Object.values(response?.data?.errors || {}).flat().join(' ')
            || response?.data?.message || 'Não foi possível confirmar a vaga. Tente novamente.';
    } finally {
        if (sequence === infoSequence) saving.value = false;
    }
}

async function chooseAnotherDate() {
    await load(false);
    await nextTick();
    datesHeading.value?.focus();
}

watch(() => route.params.slug, () => load(), { immediate: true });
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
.booking-day:disabled,
.booking-key-unavailable {
    background: var(--wn-canvas);
    color: var(--wn-muted);
}
.booking-month-arrow:disabled { opacity: 0.4; }
.booking-day:not(:disabled),
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
