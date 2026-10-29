<template>
    <main class="min-h-screen bg-[var(--wn-canvas)] px-4 py-10 text-[var(--wn-ink)] sm:py-16">
        <div class="mx-auto max-w-xl">
            <p class="mb-5 text-center text-xl font-bold">WNFit</p>
            <ActionFeedback :message="error" type="error" :action="load" />
            <section class="panel-card">
                <p v-if="loading" class="py-12 text-center text-[var(--wn-muted)]">Carregando agendamento...</p>
                <div v-else-if="unavailable" class="py-8 text-center">
                    <h1 class="text-xl font-bold">Agendamento indisponível</h1>
                    <p class="mt-3 text-sm text-[var(--wn-muted)]">Este link não está disponível. Entre em contato com o estúdio.</p>
                    <button class="btn-secondary mt-5" @click="load">Tentar novamente</button>
                </div>
                <div v-else-if="confirmation" role="status">
                    <CheckCircle2 class="mb-4 h-10 w-10 text-green-600" />
                    <h1 class="text-2xl font-bold">Agendamento confirmado</h1>
                    <p class="mt-4 font-semibold">{{ info.title }}</p>
                    <p class="mt-1">{{ displayDate(confirmation.startsAt) }}</p>
                    <p class="mt-4 text-sm text-[var(--wn-muted)]">
                        Referência: {{ confirmation.reference }}. Para alterar ou cancelar, entre em contato com o estúdio.
                    </p>
                </div>
                <template v-else-if="info">
                    <p class="text-sm font-semibold text-[var(--wn-primary-strong)]">{{ info.organization?.name || info.organization }}</p>
                    <h1 class="mt-2 text-2xl font-bold">{{ info.title }}</h1>
                    <p class="mt-2 text-sm text-[var(--wn-muted)]">{{ info.durationMinutes }} minutos · {{ info.instructorName }}</p>
                    <p v-if="info.location" class="mt-1 text-sm text-[var(--wn-muted)]">{{ info.location }}</p>
                    <form class="mt-7 space-y-5" @submit.prevent="book">
                        <label class="block text-sm font-semibold"
                            >Escolha uma data<input
                                v-model="date"
                                type="date"
                                :min="info.today"
                                :max="info.maxDate"
                                required
                                class="form-control mt-2"
                                @change="loadSlots"
                        /></label>
                        <fieldset>
                            <legend class="text-sm font-semibold">Horários disponíveis</legend>
                            <p v-if="slotsLoading" class="mt-3 text-sm text-[var(--wn-muted)]">Buscando horários...</p>
                            <div v-else-if="slots.length" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="slot in slots"
                                    :key="slot.startsAt"
                                    type="button"
                                    class="rounded-lg border px-3 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2"
                                    :class="
                                        selected === slot.startsAt
                                            ? 'border-[var(--wn-primary-strong)] bg-[var(--wn-primary)] text-black'
                                            : 'border-[var(--wn-line)]'
                                    "
                                    :aria-pressed="selected === slot.startsAt"
                                    @click="selected = slot.startsAt"
                                >
                                    {{ slot.label }}
                                </button>
                            </div>
                            <p v-else class="mt-3 text-sm text-[var(--wn-muted)]">
                                Nenhum horário disponível nesta data. Selecione outro dia.
                            </p>
                        </fieldset>
                        <div class="border-t border-[var(--wn-line)] pt-5">
                            <label class="block text-sm font-semibold"
                                >Seu nome<input
                                    v-model.trim="name"
                                    required
                                    maxlength="120"
                                    autocomplete="name"
                                    class="form-control mt-2" /></label
                            ><label class="mt-4 block text-sm font-semibold"
                                >Telefone com DDD<input
                                    v-model.trim="phone"
                                    required
                                    type="tel"
                                    maxlength="30"
                                    autocomplete="tel"
                                    class="form-control mt-2"
                                    placeholder="(11) 99999-9999"
                            /></label>
                        </div>
                        <p class="text-xs text-[var(--wn-muted)]">
                            Seus dados serão usados pelo estúdio para organizar este atendimento. Horários no fuso {{ info.timezone }}.
                        </p>
                        <button class="btn-primary w-full justify-center" :disabled="saving || !selected || slotsLoading" type="submit">
                            {{ saving ? 'Confirmando...' : 'Confirmar agendamento' }}
                        </button>
                    </form></template
                >
            </section>
        </div>
    </main>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { CheckCircle2 } from 'lucide-vue-next';
const route = useRoute(),
    info = ref(null),
    loading = ref(true),
    unavailable = ref(false),
    error = ref(''),
    date = ref(''),
    slots = ref([]),
    slotsLoading = ref(false),
    selected = ref(''),
    name = ref(''),
    phone = ref(''),
    saving = ref(false),
    confirmation = ref(null);
let requestId = crypto.randomUUID();
let slotRequest = 0;
const endpoint = `/api/public/booking/${encodeURIComponent(route.params.token)}`;
async function load() {
    loading.value = true;
    error.value = '';
    unavailable.value = false;
    try {
        const { data } = await window.axios.get(endpoint);
        info.value = data;
        date.value = data.today;
        await loadSlots();
    } catch (e) {
        if ([404, 403, 410].includes(e.response?.status)) unavailable.value = true;
        else error.value = 'Não foi possível carregar o agendamento. Tente novamente.';
    } finally {
        loading.value = false;
    }
}
async function loadSlots() {
    const sequence = ++slotRequest;
    selected.value = '';
    slots.value = [];
    slotsLoading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get(`${endpoint}/availability`, { params: { date: date.value } });
        if (sequence === slotRequest) slots.value = data.slots;
    } catch {
        error.value = 'Não foi possível consultar os horários. Selecione a data novamente.';
    } finally {
        if (sequence === slotRequest) slotsLoading.value = false;
    }
}
async function book() {
    if (!selected.value) return;
    saving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post(endpoint, { startsAt: selected.value, name: name.value, phone: phone.value, requestId });
        confirmation.value = data.booking;
    } catch (e) {
        error.value =
            Object.values(e.response?.data?.errors || {})
                .flat()
                .join(' ') ||
            e.response?.data?.message ||
            'Não foi possível confirmar. Tente novamente.';
        if (e.response?.status === 409) {
            await loadSlots();
            error.value = 'Este horário acabou de ser reservado. Escolha outro horário.';
            requestId = crypto.randomUUID();
        }
    } finally {
        saving.value = false;
    }
}
function displayDate(value) {
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full', timeStyle: 'short', timeZone: info.value.timezone }).format(
        new Date(value),
    );
}
onMounted(load);
</script>
