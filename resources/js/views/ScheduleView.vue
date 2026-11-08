<template>
    <AppShell eyebrow="Operação" title="Agenda" description="Organize aulas, atendimentos e compromissos da sua equipe.">
        <section class="summary-metrics" aria-label="Resumo da agenda">
            <article v-for="item in summaryCards" :key="item.key" class="stat-card summary-metric" :title="item.caption">
                <component :is="item.icon" class="metric-icon" aria-hidden="true" />
                <div class="metric-copy">
                    <p class="metric-label">{{ item.label }}</p>
                    <p class="metric-value">{{ item.value }}</p>
                </div>
                <span class="sr-only">{{ item.caption }}</span>
            </article>
            <button class="stat-card summary-metric agenda-copy-button" type="button" :disabled="!publicScheduleUrl" @click="copyPublicSchedule">
                <Copy class="metric-icon" aria-hidden="true" />
                <span>Copiar link de agendamento</span>
            </button>
        </section>

        <label v-if="showPublicUrl" class="block text-sm font-semibold">
            Link público de vagas
            <input class="form-control mt-1.5" :value="publicScheduleUrl" readonly @focus="$event.target.select()" />
        </label>
        <section class="panel-card mt-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <button class="icon-button" type="button" aria-label="Periodo anterior" @click="changePeriod(-1)">
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <button class="icon-button" type="button" aria-label="Proximo periodo" @click="changePeriod(1)">
                        <ChevronRight class="h-5 w-5" />
                    </button>
                    <button class="btn-secondary" type="button" @click="goToday">Hoje</button>
                    <p class="ml-1 text-lg font-bold capitalize text-[var(--wn-ink)]">{{ periodLabel }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="selectedInstructorId" class="agenda-filter" @change="loadSchedule">
                        <option value="">Todos os professores</option>
                        <option v-for="instructor in instructors" :key="instructor.id" :value="String(instructor.id)">
                            {{ instructor.name }}
                        </option>
                    </select>
                    <div class="agenda-view-switch">
                        <button :class="view === 'day' && 'agenda-view-active'" type="button" @click="view = 'day'">Dia</button
                        ><button :class="view === 'week' && 'agenda-view-active'" type="button" @click="view = 'week'">Semana</button>
                    </div>
                    <button class="btn-primary" type="button" @click="openCreate()"><Plus class="h-5 w-5" aria-hidden="true" />Novo agendamento</button>
                </div>
            </div>
        </section>

        <ActionFeedback :message="loadError" type="error" :action="reload" />
        <section v-if="loading" class="panel-card mt-6 py-16 text-center text-sm text-[var(--wn-muted)]">Carregando agenda...</section>

        <section v-else-if="view === 'day'" class="agenda-day-card mt-6">
            <div class="agenda-day-heading">
                <div>
                    <p class="text-sm font-semibold capitalize text-[var(--wn-primary-strong)]">{{ formatDate(selectedDate, 'EEEE') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-[var(--wn-ink)]">{{ formatDate(selectedDate, "d 'de' MMMM") }}</h2>
                </div>
                <p class="text-sm text-[var(--wn-muted)]">Clique em um horário livre. Áreas cinzas estão fora do expediente.</p>
            </div>
            <div ref="dayScroll" class="agenda-day-scroll">
                <div class="agenda-timeline">
                    <div class="agenda-time-labels">
                        <span v-for="hour in hours" :key="hour">{{ String(hour).padStart(2, '0') }}:00</span>
                    </div>
                    <div class="agenda-time-grid">
                        <button
                            v-for="slot in timeSlots"
                            :key="slot"
                            type="button"
                            class="agenda-time-slot"
                            :class="{ 'agenda-slot-closed': !isOpen(selectedDate, slot, slot + 15) }"
                            :disabled="!isOpen(selectedDate, slot, slot + 15)"
                            :style="{ height: '16.5px' }"
                            :aria-label="
                                isOpen(selectedDate, slot, slot + 15)
                                    ? 'Agendar às ' + minuteLabel(slot)
                                    : 'Fechado às ' + minuteLabel(slot)
                            "
                            @click="openCreate(slot / 60)"
                        ></button
                        ><button
                            v-for="event in dayEvents"
                            :key="event.id"
                            type="button"
                            class="agenda-event-card"
                            :class="[`agenda-event-${event.type}`, { 'agenda-event-cancelled': event.status === 'cancelado' }]"
                            :style="eventStyle(event)"
                            @click.stop="openDetails(event)"
                        >
                            <span class="agenda-event-time">{{ timeOf(event.startsAt) }} – {{ timeOf(event.endsAt) }}</span
                            ><strong>{{ event.title }}</strong
                            ><span>{{ event.instructorName || 'Sem professor' }} · {{ participantLabel(event) }}</span
                            ><span v-if="event.publicBooking">{{ event.publicBooking.name }} · {{ event.publicBooking.phone }}</span>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="!dayEvents.length" class="agenda-empty-state">
                <CalendarDays class="h-8 w-8" />
                <p>Nenhum compromisso neste dia.</p>
                <button class="text-sm font-semibold text-[var(--wn-primary-strong)]" @click="openCreate()">
                    Criar primeiro agendamento
                </button>
            </div>
        </section>

        <section v-else class="agenda-week-card mt-6">
            <div v-for="day in weekDays" :key="dateKey(day)" class="agenda-week-column" :class="{ 'agenda-week-today': isToday(day) }">
                <header>
                    <p>{{ formatDate(day, 'EEE') }}</p>
                    <strong>{{ formatDate(day, 'd') }}</strong>
                </header>
                <div class="space-y-2">
                    <button
                        v-for="event in eventsForDate(day)"
                        :key="event.id"
                        type="button"
                        class="agenda-week-event"
                        :class="[`agenda-event-${event.type}`, { 'opacity-50': event.status === 'cancelado' }]"
                        @click="openDetails(event)"
                    >
                        <span>{{ timeOf(event.startsAt) }} – {{ timeOf(event.endsAt) }}</span
                        ><strong>{{ event.title }}</strong
                        ><small>{{ event.instructorName || 'Sem professor' }}</small>
                    </button>
                    <p v-if="operatingHours.enforced" class="py-2 text-xs text-[var(--wn-muted)]">{{ dayHoursLabel(day) }}</p>
                    <button
                        v-if="!operatingHours.enforced || intervalsFor(day).length"
                        class="agenda-week-add"
                        type="button"
                        @click="openCreate(null, day)"
                    >
                        <Plus class="h-4 w-4" />Adicionar
                    </button>
                </div>
            </div>
        </section>

        <dialog ref="appointmentDialog" class="agenda-modal" :class="{ 'agenda-details-modal': viewingDetails }" aria-labelledby="schedule-form-title" @cancel.prevent="closeModal" @click.self="closeModal">
            <section v-if="modalOpen">
                <header class="flex items-start justify-between gap-4 border-b border-[var(--wn-line)] px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-sm font-semibold text-[var(--wn-primary-strong)]">
                            {{ viewingDetails ? 'Compromisso' : editingId ? 'Editar compromisso' : 'Novo compromisso' }}
                        </p>
                        <h2 id="schedule-form-title" class="mt-1 text-xl font-bold">
                            {{ viewingDetails ? selectedEvent.title : editingId ? 'Editar agendamento' : 'Criar agendamento' }}
                        </h2>
                    </div>
                    <button class="icon-button" type="button" aria-label="Fechar" @click="closeModal"><X class="h-5 w-5" /></button>
                </header>
                <div v-if="viewingDetails" class="agenda-form agenda-details">
                    <div class="rounded-xl bg-[var(--wn-canvas)] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-lg font-bold">{{ timeOf(selectedEvent.startsAt) }}–{{ timeOf(selectedEvent.endsAt) }}</p>
                                <p class="mt-0.5 text-[var(--wn-muted)]">{{ formatDate(selectedEvent.startsAt, 'd MMMM yyyy') }}</p>
                            </div>
                            <span class="rounded-full border border-[var(--wn-line)] bg-white px-3 py-1 text-xs font-semibold">{{ statusLabels[selectedEvent.status] }}</span>
                        </div>
                        <p v-if="dateKey(selectedEvent.startsAt) !== dateKey(selectedEvent.endsAt)" class="mt-1 text-sm">Término em {{ formatDate(selectedEvent.endsAt, 'd MMMM yyyy') }}</p>
                        <div class="mt-3 flex flex-wrap justify-between gap-2 border-t border-[var(--wn-line)] pt-3">
                            <span class="font-semibold">{{ selectedEvent.capacity ? `${selectedEvent.participantCount} de ${selectedEvent.capacity} vagas ocupadas` : `${selectedEvent.participantCount} participantes` }}</span>
                            <span v-if="selectedEvent.capacity && selectedEvent.status === 'agendado'" class="text-[var(--wn-muted)]">
                                {{ freeSpots === 0 ? 'Lotado' : freeSpots === 1 ? '1 vaga livre' : `${freeSpots} vagas livres` }}
                            </span>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4">
                        <div><dt class="font-semibold">Tipo</dt><dd>{{ types.find((type) => type.value === selectedEvent.type)?.label }}</dd></div>
                        <div v-if="selectedEvent.instructorName"><dt class="font-semibold">Professor</dt><dd>{{ selectedEvent.instructorName }}</dd></div>
                        <div><dt class="font-semibold">Modalidade</dt><dd>{{ modalityLabels[selectedEvent.modality] }}</dd></div>
                        <div v-if="selectedEvent.location"><dt class="font-semibold">Local / sala</dt><dd>{{ selectedEvent.location }}</dd></div>
                        <div v-if="selectedEvent.address"><dt class="font-semibold">Endereço</dt><dd>{{ selectedEvent.address }}</dd></div>
                        <div v-if="selectedEvent.publishSpots"><dt class="font-semibold">Agenda pública</dt><dd>Aceita reservas enquanto houver vagas</dd></div>
                    </dl>
                    <div v-if="selectedEvent.participants.length">
                        <h3 class="font-semibold">Participantes</h3>
                        <ul class="mt-1">
                            <li v-for="participant in selectedEvent.participants" :key="participant.id">{{ participant.name }}<span v-if="participant.status !== 'confirmado'"> · {{ participant.status }}</span></li>
                        </ul>
                    </div>
                    <div v-if="selectedEvent.publicReservations.length">
                        <h3 class="font-semibold">Reservas pelo link de vagas</h3>
                        <ul class="mt-2 divide-y divide-[var(--wn-line)] rounded-xl border border-[var(--wn-line)]">
                            <li v-for="reservation in selectedEvent.publicReservations" :key="reservation.id" class="px-3 py-2">
                                <p class="font-medium">{{ reservation.name }}</p>
                                <p class="text-sm text-[var(--wn-muted)]">{{ reservation.phone }} · Ref. {{ reservation.reference }}</p>
                            </li>
                        </ul>
                    </div>
                    <div v-if="editingPublicBooking" class="rounded-xl border border-[var(--wn-line)] p-4 text-sm">
                        <p class="font-semibold">Reserva pelo link de atendimento</p>
                        <p class="mt-1">{{ editingPublicBooking.name }} · {{ editingPublicBooking.phone }}</p>
                        <p v-if="editingPublicBooking.email" class="mt-1">{{ editingPublicBooking.email }}</p>
                        <p class="mt-1 text-xs text-[var(--wn-muted)]">Referência: {{ editingPublicBooking.reference }}</p>
                    </div>
                    <div v-if="selectedEvent.notes"><h3 class="font-semibold">Observações internas</h3><p class="whitespace-pre-wrap">{{ selectedEvent.notes }}</p></div>
                    <footer class="flex justify-end gap-3 border-t border-[var(--wn-line)] pt-4">
                        <button class="btn-primary" type="button" @click="openEdit(selectedEvent)">Editar agendamento</button>
                    </footer>
                </div>
                <form v-else class="agenda-form" @submit.prevent="save">
                    <div v-if="editingPublicBooking" class="rounded-xl border border-[var(--wn-line)] p-4 text-sm">
                        <p class="font-semibold">Reserva pelo link público</p>
                        <p class="mt-1">{{ editingPublicBooking.name }} · {{ editingPublicBooking.phone }}</p>
                        <p class="mt-1 text-xs text-[var(--wn-muted)]">Referência: {{ editingPublicBooking.reference }}</p>
                    </div>
                    <ActionFeedback :message="formError" type="error" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="sm:col-span-2"
                            >Título<input
                                v-model.trim="form.title"
                                class="form-control mt-1.5"
                                required
                                maxlength="120"
                                placeholder="Ex.: Aula funcional da manhã" /></label
                        ><label
                            >Tipo<select v-model="form.type" class="form-control mt-1.5">
                                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select></label
                        ><label v-if="editingId"
                            >Status<select v-model="form.status" class="form-control mt-1.5">
                                <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                            </select></label
                        ><div class="grid grid-cols-2 gap-3 sm:col-span-2 sm:grid-cols-3">
                            <label class="col-span-2 sm:col-span-1">Dia<input v-model="form.date" class="form-control mt-1.5" required type="date" /></label>
                            <label>Início<input v-model="form.startTime" class="form-control mt-1.5" required type="time" /></label>
                            <label>Fim<input v-model="form.endTime" class="form-control mt-1.5" required type="time" /></label>
                            <p v-if="legacyTiming" class="col-span-full agenda-field-hint">Este compromisso termina em outro dia. Se mudar o horário, escolha um início e um fim no mesmo dia.</p>
                        </div><label v-if="!editingId"
                            >Repetição<select v-model="form.repeatFrequency" class="form-control mt-1.5">
                                <option value="none">Não se repete</option>
                                <option value="weekly">Toda semana</option>
                                <option value="biweekly">A cada 2 semanas</option>
                                <option value="monthly">Todo mês</option>
                            </select></label
                        ><label v-if="!editingId && form.repeatFrequency !== 'none'"
                            >Repetir até<input
                                v-model="form.repeatUntil"
                                class="form-control mt-1.5"
                                :min="form.date"
                                required
                                type="date" /></label
                        ><p v-if="editingId && form.repeatFrequency !== 'none'" class="agenda-field-hint sm:col-span-2">A edição afeta apenas esta ocorrência da série.</p>
                        <label v-if="role !== 'professor'"
                            >Professor<select v-model="form.instructorId" class="form-control mt-1.5">
                                <option value="">Sem professor definido</option>
                                <option v-for="instructor in instructors" :key="instructor.id" :value="String(instructor.id)">
                                    {{ instructor.name }}
                                </option>
                            </select></label
                        ><label
                            >Modalidade<select v-model="form.modality" class="form-control mt-1.5">
                                <option value="presencial">Presencial</option>
                                <option value="externo">Externo</option>
                                <option value="online">Online</option>
                            </select></label
                        ><label v-if="form.modality === 'presencial' && (locations.length || selectedEvent?.location)"
                            >Local / sala<input
                                v-model.trim="form.location"
                                class="form-control mt-1.5"
                                maxlength="120"
                                list="agenda-locations"
                                placeholder="Ex.: Sala 1" />
                            <datalist id="agenda-locations"><option v-for="location in locations" :key="location" :value="location" /></datalist>
                        </label
                        ><label
                            >Capacidade (número de vagas)<input
                                v-model.number="form.capacity"
                                class="form-control mt-1.5"
                                type="number"
                                min="1"
                                max="1000"
                                :required="form.publishSpots"
                                placeholder="Sem limite" /></label
                        ><label v-if="form.modality === 'externo'" class="sm:col-span-2"
                            >Endereço<input
                                v-model.trim="form.address"
                                class="form-control mt-1.5"
                                maxlength="255"
                                placeholder="Local do atendimento"
                        /></label>
                        <label v-if="!editingPublicBooking" class="flex items-start gap-3 rounded-xl border border-[var(--wn-line)] p-3 sm:col-span-2">
                            <input v-model="form.publishSpots" class="mt-1" type="checkbox" />
                            <span>Permitir reservas pelo link público<small class="agenda-field-hint">O título, o horário e as vagas livres aparecem para visitantes. Cada reserva ocupa uma vaga.</small></span>
                        </label>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label>Alunos participantes</label
                            ><input v-model.trim="studentSearch" class="agenda-student-search" placeholder="Buscar aluno" />
                        </div>
                        <div class="agenda-student-list mt-2">
                            <label v-for="student in filteredStudents" :key="student.id" class="agenda-student-option"
                                ><input v-model="form.participantIds" type="checkbox" :value="student.id" /><span
                                    ><strong>{{ student.name }}</strong
                                    ><small>{{ student.phone || 'Sem telefone' }}</small></span
                                ></label
                            >
                            <p v-if="!filteredStudents.length" class="p-4 text-sm text-[var(--wn-muted)]">Nenhum aluno encontrado.</p>
                        </div>
                        <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ form.participantIds.length }} aluno(s) selecionado(s).</p>
                    </div>
                    <label
                        >Observações internas<textarea
                            v-model.trim="form.notes"
                            class="form-control mt-1.5"
                            rows="3"
                            maxlength="3000"
                            placeholder="Informações importantes para a equipe"
                        ></textarea>
                    </label>
                    <footer class="flex flex-col-reverse gap-3 border-t border-[var(--wn-line)] pt-5 sm:flex-row sm:justify-end">
                        <button class="btn-secondary justify-center" type="button" :disabled="saving" @click="editingId ? openDetails(selectedEvent) : closeModal()">Cancelar</button
                        ><button class="btn-primary justify-center" :disabled="saving" type="submit">
                            {{ saving ? 'Salvando...' : editingId ? 'Salvar alterações' : 'Criar agendamento' }}
                        </button>
                    </footer>
                </form>
            </section>
        </dialog>
    </AppShell>
</template>

<script setup>
import { notify } from '../composables/useNotifications';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { AlertCircle, CalendarDays, ChevronLeft, ChevronRight, Clock3, Copy, Plus, Users, X } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import { useRoute } from 'vue-router';
const route = useRoute();
const linkedDate = typeof route.query.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(route.query.date) ? new Date(route.query.date + 'T12:00:00') : null;
let pendingLinkedEvent = String(route.query.event || '');

const types = [
    { value: 'individual', label: 'Individual' },
    { value: 'coletiva', label: 'Coletiva' },
    { value: 'tematica', label: 'Temática' },
    { value: 'personalizada', label: 'Personalizada' },
];
const statusLabels = { agendado: 'Agendado', concluido: 'Concluído', cancelado: 'Cancelado' };
const modalityLabels = { presencial: 'Presencial', externo: 'Externo', online: 'Online' };
const timeSlots = Array.from({ length: 96 }, (_, i) => i * 15);
const operatingHours = reactive({ enforced: false, weeklyHours: {} });
const loadError = ref('');
const dayScroll = ref(null);
const hours = Array.from({ length: 24 }, (_, index) => index);
const selectedDate = ref(linkedDate && !Number.isNaN(linkedDate.getTime()) ? linkedDate : new Date());
const view = ref('week');
const events = ref([]);
const instructors = ref([]);
const students = ref([]);
const locations = ref([]);
const role = ref('');
const publicScheduleUrl = ref('');
const showPublicUrl = ref(false);
const selectedInstructorId = ref('');
const summary = reactive({ events: 0, participants: 0, availableSlots: 0 });
const summaryCards = computed(() => [
    { key: 'events', label: view.value === 'day' ? 'Compromissos no dia' : 'Compromissos na semana', value: summary.events, icon: CalendarDays, caption: 'No período selecionado' },
    { key: 'participants', label: 'Participantes confirmados', value: summary.participants, icon: Users, caption: 'Participações agendadas' },
    { key: 'spots', label: 'Vagas abertas', value: summary.availableSlots, icon: Clock3, caption: 'Nas atividades com limite' },
]);
const loading = ref(true);
const modalOpen = ref(false);
const appointmentDialog = ref(null);
const viewingDetails = ref(false);
const editingId = ref(null);
const selectedEvent = ref(null);
const saving = ref(false);
const formError = ref('');
const studentSearch = ref('');
const blankForm = () => ({
    title: '',
    type: 'coletiva',
    status: 'agendado',
    date: dateKey(selectedDate.value),
    startTime: '09:00',
    endTime: '10:00',
    repeatFrequency: 'none',
    repeatUntil: '',
    instructorId: '',
    modality: 'presencial',
    location: '',
    address: '',
    capacity: null,
    publishSpots: false,
    notes: '',
    participantIds: [],
});
const form = reactive(blankForm());
const editingPublicBooking = computed(() => selectedEvent.value?.publicBooking || null);
const freeSpots = computed(() => Math.max((selectedEvent.value?.capacity ?? 0) - (selectedEvent.value?.participantCount ?? 0), 0));
const legacyTiming = computed(() => selectedEvent.value && dateKey(selectedEvent.value.startsAt) !== dateKey(selectedEvent.value.endsAt));
const weekStart = computed(() => {
    const date = new Date(selectedDate.value);
    const weekday = date.getDay() || 7;
    date.setDate(date.getDate() - weekday + 1);
    date.setHours(0, 0, 0, 0);
    return date;
});
const weekDays = computed(() => Array.from({ length: 7 }, (_, index) => addDays(weekStart.value, index)));
const periodLabel = computed(() =>
    view.value === 'day'
        ? formatDate(selectedDate.value, "d 'de' MMMM, yyyy")
        : `${formatDate(weekDays.value[0], 'd MMM')} – ${formatDate(weekDays.value[6], 'd MMM, yyyy')}`,
);
const dayEvents = computed(() => eventsForDate(selectedDate.value));
const filteredStudents = computed(() => {
    const term = studentSearch.value.toLocaleLowerCase();
    return students.value.filter((student) => student.name.toLocaleLowerCase().includes(term));
});

function dateKey(date) {
    if (typeof date === 'string') return date.slice(0, 10);
    const value = new Date(date);
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}
function addDays(date, amount) {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    return result;
}
function formatDate(date, format) {
    const options =
        format === 'EEEE'
            ? { weekday: 'long' }
            : format === 'EEE'
              ? { weekday: 'short' }
              : format === 'd'
                ? { day: 'numeric' }
                : {
                      day: 'numeric',
                      month: format.includes('MMMM') ? 'long' : 'short',
                      year: format.includes('yyyy') ? 'numeric' : undefined,
                  };
    const value = typeof date === 'string' ? new Date(dateKey(date) + 'T12:00:00') : date;
    return new Intl.DateTimeFormat('pt-BR', options).format(value);
}
function timeOf(value) {
    return value.slice(11, 16);
}
function isToday(date) {
    return dateKey(date) === dateKey(new Date());
}
function eventsForDate(date) {
    return events.value.filter((event) => dateKey(event.startsAt) === dateKey(date));
}
function participantLabel(event) {
    if (event.publicBooking) return 'Reserva pelo link';
    return `${event.participantCount}${event.capacity ? `/${event.capacity}` : ''} participante${event.participantCount === 1 ? '' : 's'}`;
}
function eventStyle(event) {
    const start = new Date(event.startsAt);
    const end = new Date(event.endsAt);
    const minutes = minutesOf(timeOf(event.startsAt));
    return { top: `${Math.max(0, minutes * 1.1)}px`, height: `${Math.max(58, ((end - start) / 60000) * 1.1)}px` };
}
function changePeriod(direction) {
    selectedDate.value = addDays(selectedDate.value, direction * (view.value === 'day' ? 1 : 7));
}
function goToday() {
    selectedDate.value = new Date();
}
async function loadOptions() {
    const { data } = await window.axios.get('/api/schedule/options');
    instructors.value = data.instructors;
    students.value = data.students;
    locations.value = data.locations;
    role.value = data.role;
    publicScheduleUrl.value = data.publicScheduleUrl ? new URL(data.publicScheduleUrl, window.location.origin).href : '';
    Object.assign(operatingHours, data.operatingHours || {});
}
async function loadSchedule() {
    loading.value = true;
    loadError.value = '';
    try {
        const start = view.value === 'day' ? selectedDate.value : weekDays.value[0];
        const end = view.value === 'day' ? selectedDate.value : weekDays.value[6];
        const { data } = await window.axios.get('/api/schedule', {
            params: { start: dateKey(start), end: dateKey(end), instructor_id: selectedInstructorId.value || undefined },
        });
        events.value = data.events;
        if (pendingLinkedEvent) {
            const event = events.value.find((item) => String(item.id) === pendingLinkedEvent);
            pendingLinkedEvent = '';
            if (event) openDetails(event);
        }
        Object.assign(summary, data.summary);
        Object.assign(operatingHours, data.operatingHours || {});
    } catch {
        loadError.value = 'Não foi possível carregar a agenda.';
    } finally {
        loading.value = false;
        await nextTick();
        if (dayScroll.value)
            dayScroll.value.scrollTop =
                (operatingHours.enforced && intervalsFor(selectedDate.value).length
                    ? minutesOf(intervalsFor(selectedDate.value)[0].start)
                    : 360) * 1.1;
    }
}
function resetForm() {
    Object.assign(form, blankForm());
    studentSearch.value = '';
    formError.value = '';
}
function openCreate(hour = null, date = selectedDate.value) {
    resetForm();
    form.date = dateKey(date);
    const intervals = operatingHours.enforced ? intervalsFor(date) : [];
    const start = hour === null ? minutesOf(intervals[0]?.start || '09:00') : Math.round(hour * 60);
    const interval = intervals.find((item) => minutesOf(item.start) <= start && minutesOf(item.end) > start);
    form.startTime = minuteLabel(start);
    form.endTime = minuteLabel(Math.min(start + 60, minutesOf(interval?.end || '23:59')));
    editingId.value = null;
    selectedEvent.value = null;
    viewingDetails.value = false;
    showDialog();
}
function openDetails(event) {
    selectedEvent.value = event;
    viewingDetails.value = true;
    showDialog();
}
async function showDialog() {
    modalOpen.value = true;
    await nextTick();
    if (!appointmentDialog.value.open) appointmentDialog.value.showModal();
    appointmentDialog.value.scrollTop = 0;
    appointmentDialog.value.querySelector('header button')?.focus();
}
function openEdit(event) {
    editingId.value = event.id;
    Object.assign(form, {
        title: event.title,
        type: event.type,
        status: event.status,
        date: dateKey(event.startsAt),
        startTime: timeOf(event.startsAt),
        endTime: timeOf(event.endsAt),
        repeatFrequency: event.recurrenceFrequency || 'none',
        repeatUntil: event.recurrenceUntil || '',
        instructorId: event.instructorId ? String(event.instructorId) : '',
        modality: event.modality,
        location: event.location || '',
        address: event.address || '',
        capacity: event.capacity,
        publishSpots: event.publishSpots,
        notes: event.notes || '',
        participantIds: event.participants.map((participant) => participant.id),
    });
    studentSearch.value = '';
    formError.value = '';
    viewingDetails.value = false;
    showDialog();
}
function closeModal() {
    if (saving.value) return;
    appointmentDialog.value.close();
    modalOpen.value = false;
}
async function copyPublicSchedule() {
    try {
        await navigator.clipboard.writeText(publicScheduleUrl.value);
        notify('Link público de vagas copiado.');
    } catch {
        showPublicUrl.value = true;
    }
}
async function save() {
    if (saving.value) return;
    formError.value = '';
    const original = selectedEvent.value;
    const unchangedTiming = original && form.date === dateKey(original.startsAt)
        && form.startTime === timeOf(original.startsAt) && form.endTime === timeOf(original.endsAt);
    if (!unchangedTiming && form.endTime <= form.startTime) {
        formError.value = 'O horário de fim precisa ser depois do início.';
        return;
    }
    saving.value = true;
    try {
        const payload = {
            title: form.title,
            type: form.type,
            status: form.status,
            starts_at: unchangedTiming ? original.startsAt : `${form.date}T${form.startTime}`,
            ends_at: unchangedTiming ? original.endsAt : `${form.date}T${form.endTime}`,
            ...(editingId.value ? {} : {
                repeat_frequency: form.repeatFrequency,
                repeat_until: form.repeatFrequency === 'none' ? null : form.repeatUntil,
            }),
            instructor_id: form.instructorId || null,
            modality: form.modality,
            location: form.modality === 'presencial' || form.modality === original?.modality ? form.location || null : null,
            address: form.modality === 'externo' || form.modality === original?.modality ? form.address || null : null,
            capacity: form.capacity === '' ? null : form.capacity,
            publish_spots: form.publishSpots,
            notes: form.notes || null,
            participant_ids: form.participantIds,
        };
        await window.axios[editingId.value ? 'put' : 'post'](
            editingId.value ? `/api/schedule/${editingId.value}` : '/api/schedule',
            payload,
        );
        notify(editingId.value ? 'Agendamento atualizado.' : 'Agendamento criado.');
        saving.value = false;
        closeModal();
        if (dateKey(selectedDate.value) !== form.date) selectedDate.value = new Date(form.date + 'T12:00:00');
        else await loadSchedule();
    } catch (error) {
        const messages = error.response?.data?.errors;
        formError.value = messages
            ? Object.values(messages).flat().join(' ')
            : error.response?.data?.message || 'Não foi possível salvar o agendamento.';
    } finally {
        saving.value = false;
    }
}
function minutesOf(value) {
    const [h, m] = value.split(':').map(Number);
    return h * 60 + m;
}
function minuteLabel(value) {
    return String(Math.floor(value / 60)).padStart(2, '0') + ':' + String(value % 60).padStart(2, '0');
}
function intervalsFor(date) {
    return operatingHours.weeklyHours?.[new Date(date).getDay()] || [];
}
function isOpen(date, start, end) {
    return !operatingHours.enforced || intervalsFor(date).some((item) => minutesOf(item.start) <= start && minutesOf(item.end) >= end);
}
function dayHoursLabel(day) {
    return (
        intervalsFor(day)
            .map((item) => item.start + '–' + item.end)
            .join(' · ') || 'Fechado'
    );
}
async function reload() {
    try {
        await loadOptions();
        await loadSchedule();
    } catch {
        loading.value = false;
        loadError.value = 'Não foi possível carregar a agenda.';
    }
}
watch([selectedDate, view], loadSchedule);
onMounted(reload);
</script>

<style scoped>
.agenda-copy-button {
    justify-content: center;
    color: var(--wn-ink);
    text-align: center;
    font-size: 0.875rem;
    font-weight: 700;
}
.agenda-copy-button .metric-icon { color: inherit; }
.agenda-copy-button:not(:disabled) {
    border-color: var(--wn-primary);
    background: var(--wn-primary);
    cursor: pointer;
}
.agenda-copy-button:not(:disabled):hover { filter: brightness(0.96); }
.agenda-copy-button:focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: 2px; }
.agenda-copy-button:disabled { opacity: 0.5; cursor: not-allowed; }
.agenda-modal {
    width: min(calc(100% - 2rem), 51rem);
    margin: auto;
    max-height: calc(100dvh - 2rem);
    padding: 0;
}
.agenda-modal::backdrop { background: rgb(15 23 42 / 50%); }
.agenda-details-modal { width: min(calc(100% - 2rem), 34rem); }
.agenda-details { font-weight: 400; }
.agenda-time-grid {
    height: 1584px;
}
.agenda-time-slot.agenda-slot-closed {
    cursor: not-allowed;
    background: repeating-linear-gradient(135deg, #eef0ed, #eef0ed 5px, #e5e8e3 5px, #e5e8e3 6px);
    opacity: 0.8;
}
.agenda-time-slot:not(:disabled):focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: -2px;
}
</style>
