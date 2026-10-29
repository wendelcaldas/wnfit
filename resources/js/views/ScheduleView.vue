<template>
    <AppShell eyebrow="Operacao" title="Agenda" description="Organize aulas, atendimentos e compromissos da sua equipe.">
        <template #page-actions>
            <button class="btn-primary" type="button" @click="openCreate()"><Plus class="h-5 w-5" />Novo agendamento</button>
        </template>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="agenda-stat-icon"><CalendarDays class="h-5 w-5" /></div>
                    <div>
                        <p class="text-sm font-medium text-[var(--wn-muted)]">Compromissos</p>
                        <p class="mt-1 text-3xl font-bold">{{ summary.events }}</p>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">no periodo selecionado</p>
                    </div>
                </div>
            </article>
            <article class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="agenda-stat-icon"><Users class="h-5 w-5" /></div>
                    <div>
                        <p class="text-sm font-medium text-[var(--wn-muted)]">Alunos confirmados</p>
                        <p class="mt-1 text-3xl font-bold">{{ summary.participants }}</p>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">participacoes agendadas</p>
                    </div>
                </div>
            </article>
            <article class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="agenda-stat-icon"><Clock3 class="h-5 w-5" /></div>
                    <div>
                        <p class="text-sm font-medium text-[var(--wn-muted)]">Vagas abertas</p>
                        <p class="mt-1 text-3xl font-bold">{{ summary.availableSlots }}</p>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">nas atividades com limite</p>
                    </div>
                </div>
            </article>
        </section>

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
                    <button class="btn-primary md:hidden" type="button" @click="openCreate()"><Plus class="h-5 w-5" />Novo</button>
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
                            @click.stop="openEdit(event)"
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
                        @click="openEdit(event)"
                    >
                        <span>{{ timeOf(event.startsAt) }}</span
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

        <div v-if="modalOpen" class="agenda-modal-backdrop" @click.self="closeModal">
            <section class="agenda-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-form-title">
                <header class="flex items-start justify-between gap-4 border-b border-[var(--wn-line)] px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-sm font-semibold text-[var(--wn-primary-strong)]">
                            {{ editingId ? 'Edicao de agenda' : 'Novo compromisso' }}
                        </p>
                        <h2 id="schedule-form-title" class="mt-1 text-xl font-bold">
                            {{ editingId ? form.title || 'Editar agendamento' : 'Criar agendamento' }}
                        </h2>
                    </div>
                    <button class="icon-button" type="button" aria-label="Fechar" @click="closeModal"><X class="h-5 w-5" /></button>
                </header>
                <form class="agenda-form" @submit.prevent="save">
                    <div v-if="editingPublicBooking" class="rounded-xl border border-[var(--wn-line)] p-4 text-sm">
                        <p class="font-semibold">Reserva pelo link público</p>
                        <p class="mt-1">{{ editingPublicBooking.name }} · {{ editingPublicBooking.phone }}</p>
                        <p class="mt-1 text-xs text-[var(--wn-muted)]">Referência: {{ editingPublicBooking.reference }}</p>
                    </div>
                    <ActionFeedback :message="formError" type="error" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="sm:col-span-2"
                            >Titulo<input
                                v-model.trim="form.title"
                                class="form-control mt-1.5"
                                required
                                maxlength="120"
                                placeholder="Ex.: Aula funcional - turma da manha" /></label
                        ><label
                            >Tipo<select v-model="form.type" class="form-control mt-1.5">
                                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select></label
                        ><label
                            >Status<select v-model="form.status" class="form-control mt-1.5">
                                <option value="agendado">Agendado</option>
                                <option value="concluido">Concluido</option>
                                <option value="cancelado">Cancelado</option>
                            </select></label
                        ><label>Inicio<input v-model="form.startsAt" class="form-control mt-1.5" required type="datetime-local" /></label
                        ><label>Termino<input v-model="form.endsAt" class="form-control mt-1.5" required type="datetime-local" /></label
                        ><label
                            >Repeticao<select v-model="form.repeatFrequency" class="form-control mt-1.5" :disabled="Boolean(editingId)">
                                <option value="none">Nao se repete</option>
                                <option value="weekly">Toda semana</option>
                                <option value="biweekly">A cada 2 semanas</option>
                                <option value="monthly">Todo mes</option></select
                            ><small v-if="editingId && form.repeatFrequency !== 'none'" class="agenda-field-hint"
                                >Edite esta ocorrencia sem alterar a serie.</small
                            ></label
                        ><label v-if="form.repeatFrequency !== 'none'"
                            >Repetir ate<input
                                v-model="form.repeatUntil"
                                class="form-control mt-1.5"
                                :disabled="Boolean(editingId)"
                                required
                                type="date" /></label
                        ><label
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
                        ><label
                            >Local / sala<input
                                v-model.trim="form.location"
                                class="form-control mt-1.5"
                                maxlength="120"
                                placeholder="Ex.: Sala 1" /></label
                        ><label
                            >Capacidade<input
                                v-model.number="form.capacity"
                                class="form-control mt-1.5"
                                type="number"
                                min="1"
                                max="1000"
                                placeholder="Ilimitada" /></label
                        ><label v-if="form.modality === 'externo'" class="sm:col-span-2"
                            >Endereco<input
                                v-model.trim="form.address"
                                class="form-control mt-1.5"
                                maxlength="255"
                                placeholder="Local do atendimento"
                        /></label>
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
                        >Observacoes internas<textarea
                            v-model.trim="form.notes"
                            class="form-control mt-1.5"
                            rows="3"
                            maxlength="3000"
                            placeholder="Informacoes importantes para a equipe"
                        ></textarea>
                    </label>
                    <footer class="flex flex-col-reverse gap-3 border-t border-[var(--wn-line)] pt-5 sm:flex-row sm:justify-end">
                        <button class="btn-secondary justify-center" type="button" @click="closeModal">Cancelar</button
                        ><button class="btn-primary justify-center" :disabled="saving" type="submit">
                            {{ saving ? 'Salvando...' : editingId ? 'Salvar alteracoes' : 'Criar agendamento' }}
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import { notify } from '../composables/useNotifications';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { AlertCircle, CalendarDays, ChevronLeft, ChevronRight, Clock3, Plus, Users, X } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import { useRoute } from 'vue-router';
const route = useRoute();
const linkedDate = typeof route.query.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(route.query.date) ? new Date(route.query.date + 'T12:00:00') : null;
let pendingLinkedEvent = String(route.query.event || '');

const types = [
    { value: 'individual', label: 'Individual' },
    { value: 'coletiva', label: 'Coletiva' },
    { value: 'tematica', label: 'Tematica' },
    { value: 'personalizada', label: 'Personalizada' },
];
const timeSlots = Array.from({ length: 96 }, (_, i) => i * 15);
const operatingHours = reactive({ enforced: false, weeklyHours: {} });
const loadError = ref('');
const dayScroll = ref(null);
const hours = Array.from({ length: 24 }, (_, index) => index);
const selectedDate = ref(linkedDate && !Number.isNaN(linkedDate.getTime()) ? linkedDate : new Date());
const view = ref('day');
const events = ref([]);
const instructors = ref([]);
const students = ref([]);
const selectedInstructorId = ref('');
const summary = reactive({ events: 0, participants: 0, availableSlots: 0 });
const loading = ref(true);
const modalOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const studentSearch = ref('');
const blankForm = () => ({
    title: '',
    type: 'coletiva',
    status: 'agendado',
    startsAt: dateTimeFor(selectedDate.value, 9),
    endsAt: dateTimeFor(selectedDate.value, 10),
    repeatFrequency: 'none',
    repeatUntil: '',
    instructorId: '',
    modality: 'presencial',
    location: '',
    address: '',
    capacity: null,
    notes: '',
    participantIds: [],
});
const form = reactive(blankForm());
const editingPublicBooking = computed(() => events.value.find((event) => event.id === editingId.value)?.publicBooking || null);
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
    return new Intl.DateTimeFormat('pt-BR', options).format(new Date(date));
}
function dateTimeFor(date, hour) {
    const value = new Date(date);
    value.setHours(Math.floor(hour), Math.round((hour % 1) * 60), 0, 0);
    return `${dateKey(value)}T${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}`;
}
function toInputDateTime(value) {
    const date = new Date(value);
    return dateTimeFor(date, date.getHours()).replace(
        /T\d{2}:\d{2}$/,
        `T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`,
    );
}
function timeOf(value) {
    return new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}
function isToday(date) {
    return dateKey(date) === dateKey(new Date());
}
function eventsForDate(date) {
    return events.value.filter((event) => dateKey(event.startsAt) === dateKey(date));
}
function participantLabel(event) {
    if (event.publicBooking) return 'Reserva pelo link';
    return `${event.participantCount}${event.capacity ? `/${event.capacity}` : ''} aluno${event.participantCount === 1 ? '' : 's'}`;
}
function eventStyle(event) {
    const start = new Date(event.startsAt);
    const end = new Date(event.endsAt);
    const minutes = start.getHours() * 60 + start.getMinutes();
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
            if (event) openEdit(event);
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
    selectedDate.value = new Date(date);
    if (hour === null && operatingHours.enforced) {
        const intervals = intervalsFor(date);
        if (!intervals.length) {
            notify('Este dia está fechado. Escolha outro dia.', { type: 'info' });
            return;
        }
        hour = minutesOf(intervals[0].start) / 60;
    }
    if (hour !== null) {
        form.startsAt = dateTimeFor(date, hour);
        const end = operatingHours.enforced
            ? Math.min(
                  hour * 60 + 60,
                  minutesOf(
                      intervalsFor(date).find((item) => minutesOf(item.start) <= hour * 60 && minutesOf(item.end) > hour * 60)?.end ||
                          '23:00',
                  ),
              ) / 60
            : Math.min(hour + 1, 24);
        form.endsAt = dateTimeFor(date, end);
    }
    editingId.value = null;
    modalOpen.value = true;
}
function openEdit(event) {
    editingId.value = event.id;
    Object.assign(form, {
        title: event.title,
        type: event.type,
        status: event.status,
        startsAt: toInputDateTime(event.startsAt),
        endsAt: toInputDateTime(event.endsAt),
        repeatFrequency: event.recurrenceFrequency || 'none',
        repeatUntil: event.recurrenceUntil || '',
        instructorId: event.instructorId ? String(event.instructorId) : '',
        modality: event.modality,
        location: event.location || '',
        address: event.address || '',
        capacity: event.capacity,
        notes: event.notes || '',
        participantIds: event.participants.map((participant) => participant.id),
    });
    studentSearch.value = '';
    formError.value = '';
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
}
async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = {
            title: form.title,
            type: form.type,
            status: form.status,
            starts_at: form.startsAt,
            ends_at: form.endsAt,
            repeat_frequency: form.repeatFrequency,
            repeat_until: form.repeatFrequency === 'none' ? null : form.repeatUntil,
            instructor_id: form.instructorId || null,
            modality: form.modality,
            location: form.location || null,
            address: form.address || null,
            capacity: form.capacity || null,
            notes: form.notes || null,
            participant_ids: form.participantIds,
        };
        const { data } = await window.axios[editingId.value ? 'put' : 'post'](
            editingId.value ? `/api/schedule/${editingId.value}` : '/api/schedule',
            payload,
        );
        const index = events.value.findIndex((event) => event.id === data.event.id);
        if (index >= 0) events.value.splice(index, 1, data.event);
        else events.value.push(data.event);
        notify(editingId.value ? 'Agendamento atualizado.' : 'Agendamento criado.');
        closeModal();
        await loadSchedule();
    } catch (error) {
        const messages = error.response?.data?.errors;
        formError.value = messages
            ? Object.values(messages).flat().join(' ')
            : error.response?.data?.message || 'Nao foi possivel salvar o agendamento.';
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
