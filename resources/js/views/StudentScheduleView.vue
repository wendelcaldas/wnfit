<template>
    <StudentShell>
        <h1 class="text-center font-display text-3xl font-bold">
            {{ auth.student?.organization }}
        </h1>

        <p v-if="loading" class="student-card mt-6" role="status">Carregando agenda…</p>
        <div v-else-if="loadError" class="student-error mt-6" role="alert">
            {{ loadError }}
            <button class="underline" @click="load">Tentar novamente</button>
        </div>
        <template v-else>
            <section class="student-card mt-6">
                <h2 class="text-lg font-bold">Escolha uma data para agendamento</h2>
                <BookingCalendar
                    :today="today" :max-date="maxDate" :month="month"
                    :date="date" :available-dates="availableDates" :disabled="reserving !== null"
                    @change-month="changeMonth" @select-date="openAvailability"
                />
            </section>
            <section class="mt-6">
                <h2 id="meus-compromissos" ref="commitmentsHeading" tabindex="-1" class="scroll-mt-6 text-lg font-bold">
                    Meus compromissos
                </h2>
                <p v-if="success" class="badge-success mt-3" role="status">{{ success }}</p>
                <p v-if="!confirmedEvents.length" class="student-card mt-3 text-sm text-[var(--wn-muted)]">
                    Você ainda não tem compromissos confirmados.
                </p>
                <ul v-else class="mt-3 space-y-3">
                    <li v-for="event in confirmedEvents" :key="event.id" class="student-card">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold capitalize text-[var(--wn-muted)]">
                                    {{ formatDate(event.date) }} · {{ event.start }}–{{ event.end }}
                                </p>
                                <h3 class="mt-1 font-bold">{{ event.title }}</h3>
                                <p v-if="event.location || modalities[event.modality]" class="mt-1 text-sm text-[var(--wn-muted)]">
                                    {{ event.location || modalities[event.modality] }}
                                </p>
                            </div>
                            <span class="badge-success">Confirmado</span>
                        </div>
                        <button
                            type="button"
                            class="btn-secondary mt-4 w-full justify-center !text-rose-700 sm:w-auto"
                            :aria-label="`Cancelar agendamento de ${event.title} em ${formatDate(event.date)} às ${event.start}`"
                            :disabled="reserving !== null"
                            @click="confirmCancellation(event)"
                        >
                            Cancelar agendamento
                        </button>
                    </li>
                </ul>
            </section>
        </template>
        <dialog
            ref="availabilityDialog"
            class="m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-md flex-col overflow-hidden rounded-2xl border border-[var(--wn-line)] bg-white text-[var(--wn-ink)] open:flex backdrop:bg-black/50"
            aria-labelledby="student-availability-title"
            aria-describedby="student-availability-date"
            @cancel="reserving !== null && $event.preventDefault()"
        >
            <header class="flex shrink-0 items-start justify-between gap-3 px-5 pt-5 pb-4">
                <div>
                    <h2 id="student-availability-title" class="text-lg font-bold">Horários disponíveis</h2>
                    <p id="student-availability-date" class="mt-1 text-sm capitalize text-[var(--wn-muted)]">
                        {{ date ? formatDate(date) : '' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="grid size-11 shrink-0 place-items-center rounded-lg hover:bg-[var(--wn-surface-soft)] disabled:opacity-50"
                    aria-label="Fechar horários disponíveis"
                    autofocus
                    :disabled="reserving !== null"
                    @click="availabilityDialog.close()"
                >
                    <X :size="22" aria-hidden="true" />
                </button>
            </header>
            <div ref="availabilityBody" class="min-h-0 overflow-y-auto overscroll-contain px-5 pb-5">
                <p v-if="reservationError" class="student-error mb-3" role="alert">{{ reservationError }}</p>
                <p v-if="!availableOnDate.length" class="text-sm text-[var(--wn-muted)]">
                    Nenhuma vaga disponível para esta data.
                </p>
                <ul v-else class="space-y-3">
                    <li v-for="event in availableOnDate" :key="event.id" class="student-card">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-[var(--wn-muted)]">{{ event.start }}–{{ event.end }}</p>
                                <h3 class="mt-1 font-bold">{{ event.title }}</h3>
                                <p v-if="event.location || modalities[event.modality]" class="mt-1 text-sm text-[var(--wn-muted)]">
                                    {{ event.location || modalities[event.modality] }}
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-[var(--wn-muted)]">
                                {{ event.availableSpots }} {{ event.availableSpots === 1 ? 'vaga' : 'vagas' }}
                            </span>
                        </div>
                        <button
                            type="button"
                            class="btn-primary mt-4 w-full justify-center"
                            :aria-label="`Reservar vaga para ${event.title} às ${event.start}`"
                            :disabled="reserving !== null"
                            @click="reserve(event)"
                        >
                            {{ reserving === event.id ? 'Reservando…' : 'Reservar vaga' }}
                        </button>
                    </li>
                </ul>
            </div>
        </dialog>
        <dialog
            ref="cancelDialog"
            class="m-auto w-[calc(100%_-_2rem)] max-w-md rounded-2xl border border-[var(--wn-line)] bg-white p-5 text-[var(--wn-ink)] backdrop:bg-black/50"
            aria-labelledby="student-cancel-title"
            aria-describedby="student-cancel-description"
            @cancel="cancelling && $event.preventDefault()"
        >
            <h2 id="student-cancel-title" class="text-lg font-bold">Cancelar agendamento?</h2>
            <p v-if="cancelTarget" id="student-cancel-description" class="mt-2 text-sm text-[var(--wn-muted)]">
                Você vai liberar sua vaga em {{ cancelTarget.title }}, dia {{ formatDate(cancelTarget.date) }} às {{ cancelTarget.start }}.
            </p>
            <p v-if="cancelError" class="student-error mt-3" role="alert">{{ cancelError }}</p>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button" class="btn-secondary" autofocus :disabled="cancelling" @click="cancelDialog.close()">
                    Manter agendamento
                </button>
                <button type="button" class="btn-primary" :disabled="cancelling" @click="cancelAppointment">
                    {{ cancelling ? 'Cancelando…' : 'Confirmar cancelamento' }}
                </button>
            </div>
        </dialog>
    </StudentShell>
</template>

<script setup>
import { X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import BookingCalendar from '../components/BookingCalendar.vue';
import StudentShell from '../components/StudentShell.vue';
import { useStudentAuthStore } from '../stores/studentAuth';

const auth = useStudentAuthStore();
const events = ref([]);
const today = ref('');
const maxDate = ref('');
const month = ref('');
const date = ref('');
const loading = ref(true);
const loadError = ref('');
const reserving = ref(null);
const reservationError = ref('');
const success = ref('');
const availabilityDialog = ref(null);
const availabilityBody = ref(null);
const commitmentsHeading = ref(null);
const cancelDialog = ref(null);
const cancelTarget = ref(null);
const cancelling = ref(false);
const cancelError = ref('');
const modalities = { presencial: 'Presencial', externo: 'Externo', online: 'Online' };
const formatter = new Intl.DateTimeFormat('pt-BR', {
    weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC',
});
const formatDate = (date) => formatter.format(new Date(date));

const availableEvents = computed(() => events.value.filter((event) => !event.confirmed));
const availableDates = computed(() => [...new Set(availableEvents.value.map((event) => event.date))]);
const availableOnDate = computed(() => availableEvents.value.filter((event) => event.date === date.value));
const confirmedEvents = computed(() => events.value.filter((event) => event.confirmed));

function changeMonth(nextMonth) {
    month.value = nextMonth;
    date.value = '';
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await window.axios.get('/api/student/schedule');
        today.value = data.today;
        maxDate.value = data.maxDate;
        if (month.value < data.today.slice(0, 7) || month.value > data.maxDate.slice(0, 7)) {
            changeMonth(data.today.slice(0, 7));
        }
        events.value = data.events;
    } catch {
        loadError.value = 'Não foi possível carregar sua agenda.';
    } finally {
        loading.value = false;
    }
}

async function openAvailability(day) {
    date.value = day;
    reservationError.value = '';
    await nextTick();
    availabilityDialog.value.showModal();
    availabilityBody.value.scrollTop = 0;
}

async function reserve(event) {
    if (reserving.value !== null) return;
    reserving.value = event.id;
    success.value = '';
    reservationError.value = '';
    try {
        const { data } = await window.axios.post(`/api/student/schedule/${event.id}/reservations`, {
            date: event.date,
            start: event.start,
            end: event.end,
        });
        events.value = events.value.map((item) => item.id === event.id ? data.event : item);
        success.value = `Vaga confirmada para ${data.event.title}.`;
        availabilityDialog.value.close();
        await nextTick();
        commitmentsHeading.value?.focus();
    } catch (error) {
        if (error.response?.status === 409) {
            reservationError.value = error.response.data.message;
            await load();
        } else {
            reservationError.value = 'Não foi possível confirmar a vaga. Tente novamente.';
        }
    } finally {
        reserving.value = null;
    }
}

async function confirmCancellation(event) {
    cancelTarget.value = event;
    cancelError.value = '';
    await nextTick();
    cancelDialog.value.showModal();
}

async function cancelAppointment() {
    if (cancelling.value || !cancelTarget.value) return;
    const event = cancelTarget.value;
    cancelling.value = true;
    cancelError.value = '';
    try {
        await window.axios.delete(`/api/student/schedule/${event.id}/reservations`);
        cancelDialog.value.close();
        events.value = events.value.filter((item) => item.id !== event.id);
        success.value = `Agendamento cancelado para ${event.title}.`;
        await load();
        await nextTick();
        commitmentsHeading.value?.focus();
    } catch (error) {
        cancelError.value = error.response?.status === 409
            ? error.response.data.message
            : 'Não foi possível cancelar o agendamento. Tente novamente.';
    } finally {
        cancelling.value = false;
    }
}

onMounted(load);
</script>
