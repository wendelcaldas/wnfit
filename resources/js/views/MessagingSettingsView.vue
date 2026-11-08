<template>
    <AppShell
        class="messages-page"
        visual-refresh
        eyebrow="Mensagens"
        title="Mensagens"
        description="Defina quais mensagens o WNFit deve enviar em cada etapa."
    >
        <template #page-actions>
            <button class="btn-primary justify-center" type="submit" form="message-preferences" :disabled="loading || saving || !hasChanges">
                {{ saving ? 'Salvando…' : 'Salvar preferências' }}
            </button>
        </template>

        <p class="mb-5 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] px-4 py-3 text-sm text-[var(--wn-muted)]">
            As boas-vindas já seguem a configuração abaixo. Os eventos de Treino, Financeiro e Agenda guardam as preferências para quando os envios estiverem disponíveis.
        </p>

        <p v-if="loading" class="text-sm text-[var(--wn-muted)]" role="status">Carregando preferências…</p>
        <div v-else-if="loadError" class="panel-card flex flex-wrap items-center justify-between gap-3">
            <p role="alert">{{ loadError }}</p>
            <button class="btn-secondary" type="button" @click="loadPreferences">Tentar novamente</button>
        </div>

        <form v-else id="message-preferences" class="message-layout" @submit.prevent="savePreferences">
            <div class="message-list grid min-w-0 content-start gap-5" role="region" aria-label="Lista de mensagens" tabindex="0">
                <section v-for="group in groups" :key="group.name" class="panel-card" :aria-labelledby="'message-group-' + group.events[0].id">
                    <div class="flex items-center gap-3 border-b border-[var(--wn-line)] pb-4">
                        <span class="grid size-10 place-items-center rounded-xl bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <component :is="group.icon" class="size-5" aria-hidden="true" />
                        </span>
                        <h2 :id="'message-group-' + group.events[0].id" class="text-lg font-semibold">{{ group.name }}</h2>
                    </div>
                    <ul class="divide-y divide-[var(--wn-line)]">
                        <li v-for="event in group.events" :key="event.id" class="py-4 last:pb-0">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-transparent px-3 py-2.5 text-left hover:bg-[var(--wn-surface-soft)] focus-visible:outline-2 focus-visible:outline-[var(--wn-primary-strong)] aria-pressed:border-[var(--wn-line)] aria-pressed:bg-[var(--wn-primary-soft)]"
                                    :aria-label="'Ver prévia de ' + event.title"
                                    :aria-pressed="selectedEvent.id === event.id"
                                    aria-controls="message-preview"
                                    @click="showPreview(event)"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ event.title }}</span>
                                        <span class="mt-1 block text-sm text-[var(--wn-muted)]">{{ event.description }}</span>
                                        <span class="mt-1 block text-xs font-medium text-[var(--wn-primary-strong)]">
                                            {{ selectedEvent.id === event.id ? 'Em exibição' : 'Ver prévia' }}
                                        </span>
                                    </span>
                                </button>

                                <label class="grid min-h-11 shrink-0 cursor-pointer place-items-center content-center gap-1">
                                    <span class="relative">
                                        <input v-model="preferences[event.id]" class="peer sr-only" type="checkbox" role="switch" :aria-label="'Preferência de envio: ' + event.title" :disabled="saving" />
                                        <span class="block h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-[var(--wn-primary-strong)] peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--wn-primary-strong)] peer-focus-visible:ring-offset-2 peer-disabled:opacity-60" aria-hidden="true"></span>
                                        <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
                                    </span>
                                    <span class="text-xs" :class="preferences[event.id] ? 'text-[var(--wn-primary-strong)]' : 'text-[var(--wn-muted)]'" aria-hidden="true">
                                        {{ preferences[event.id] ? 'Ativada' : 'Desativada' }}
                                    </span>
                                </label>
                            </div>
                            <label v-if="event.id === 'cobranca_atrasada'" class="mt-3 grid max-w-xs gap-1.5 text-sm">
                                <span class="font-medium">Prazo após o vencimento</span>
                                <select v-model.number="overdueDelayDays" class="form-control" :disabled="saving">
                                    <option v-for="days in 30" :key="days" :value="days">
                                        {{ days === 1 ? '1 dia' : days + ' dias' }}
                                    </option>
                                </select>
                            </label>
                        </li>
                    </ul>
                </section>
            </div>

            <aside id="message-preview" ref="previewPanel" class="message-preview" aria-labelledby="message-preview-title" tabindex="-1">
                <h2 id="message-preview-title" class="mb-3 text-center text-lg font-semibold">Prévia da mensagem</h2>
                <div class="message-phone mx-auto">
                    <div class="message-phone-screen">
                        <div class="bg-[#f7f8fa] text-[#111b21]">
                            <div class="message-phone-status flex items-center justify-between px-4 text-xs font-semibold" aria-hidden="true">
                                <span>09:41</span>
                                <span class="message-phone-camera"></span>
                                <div class="flex items-center gap-1"><Signal class="size-3" /><Wifi class="size-3" /><BatteryFull class="size-4" /></div>
                            </div>
                            <div class="flex items-center gap-2 border-b border-black/5 px-2.5 py-2">
                                <ChevronLeft class="size-5 shrink-0" aria-hidden="true" />
                                <img :src="senderAvatar" alt="" width="34" height="34" class="size-[34px] shrink-0 rounded-full object-cover" />
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold">WNFIT</span>
                                <Video class="size-4 shrink-0" aria-hidden="true" />
                                <Phone class="ml-2 size-4 shrink-0" aria-hidden="true" />
                            </div>
                        </div>

                        <div class="message-chat flex min-h-0 flex-col overflow-y-auto px-3 py-3" aria-live="polite">
                            <div class="mt-auto shrink-0">
                                <p class="mx-auto mb-3 w-fit rounded-lg bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-[#54656f] shadow-sm">Hoje</p>
                                <div class="message-bubble max-w-[92%] rounded-lg rounded-tl-none bg-white px-2.5 py-1.5 text-[13px] text-[#111b21] shadow-sm">
                                    <p class="whitespace-pre-wrap break-words leading-snug">{{ previewMessage }}</p>
                                    <p class="mt-0.5 text-right text-[10px] text-[#54656f]" aria-hidden="true">09:41</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-[#f7f8fa] text-[#111b21]" aria-hidden="true">
                            <div class="flex items-center gap-2 px-2.5 py-2">
                                <Plus class="size-5 shrink-0" />
                                <div class="flex h-7 min-w-0 flex-1 items-center justify-end rounded-full border border-black/10 bg-white px-2"><Sticker class="size-3.5" /></div>
                                <Camera class="size-4 shrink-0" />
                                <Mic class="size-4 shrink-0" />
                            </div>
                            <div class="pb-1.5 pt-1"><div class="mx-auto h-1 w-20 rounded-full bg-[#111b21]"></div></div>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-[var(--wn-muted)]">Simulação com dados de exemplo</p>
            </aside>
        </form>
    </AppShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { BatteryFull, CalendarDays, Camera, ChevronLeft, Dumbbell, Mic, Phone, Plus, Signal, Sticker, UserRound, Video, Wallet, Wifi } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import { notify } from '../composables/useNotifications';

const groups = [
    { name: 'Aluno', icon: UserRound, events: [
        { id: 'boas_vindas', title: 'Boas-vindas', description: 'Ao cadastrar um novo aluno.' },
    ] },
    { name: 'Treino', icon: Dumbbell, events: [
        { id: 'treino_atualizado', title: 'Treino disponível', description: 'Ao vincular ou atualizar a ficha do aluno.', preview: 'Olá, Mariana! Seu treino Força e mobilidade está disponível. Acesse a área do aluno para ver a ficha.' },
    ] },
    { name: 'Financeiro', icon: Wallet, events: [
        { id: 'vencimento_proximo', title: 'Aviso de vencimento', description: 'Dois dias antes do vencimento.', preview: 'Olá, Mariana! Sua mensalidade de R$ 150,00 vence em dois dias, em 10/11/2026.' },
        { id: 'vencimento_hoje', title: 'Vencimento hoje', description: 'No dia do vencimento.', preview: 'Olá, Mariana! Sua mensalidade de R$ 150,00 vence hoje.' },
        { id: 'cobranca_atrasada', title: 'Cobrança em atraso', description: 'Quando uma cobrança estiver em atraso.', preview: 'Olá, Mariana! Sua mensalidade de R$ 150,00 está em aberto. Se você já pagou, pode desconsiderar este aviso.' },
    ] },
    { name: 'Agenda', icon: CalendarDays, events: [
        { id: 'agendamento_confirmado', title: 'Agendamento confirmado', description: 'Ao reservar pelo link público ou quando o gestor incluir o aluno no agendamento.', preview: 'Olá, Mariana! Seu agendamento para Pilates está confirmado para 10/11/2026 às 08:00.' },
    ] },
];

const preferences = ref({});
const savedPreferences = ref({});
const overdueDelayDays = ref(1);
const savedOverdueDelayDays = ref(1);
const welcomePreview = ref('');
const selectedEvent = ref(groups[0].events[0]);
const previewPanel = ref(null);
const senderAvatar = '/icons/wnfit.svg';
const previewMessage = computed(() => selectedEvent.value.id === 'boas_vindas' ? welcomePreview.value : selectedEvent.value.preview);
const loading = ref(true);
const loadError = ref('');
const saving = ref(false);
const hasChanges = computed(() => overdueDelayDays.value !== savedOverdueDelayDays.value
    || Object.keys(savedPreferences.value).some((key) => preferences.value[key] !== savedPreferences.value[key]));

function showPreview(event) {
    selectedEvent.value = event;
    if (!window.matchMedia('(min-width: 64rem)').matches) previewPanel.value?.focus();
}

async function loadPreferences() {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await window.axios.get('/api/organization/messaging');
        savedPreferences.value = data.settings.events;
        preferences.value = { ...savedPreferences.value };
        overdueDelayDays.value = savedOverdueDelayDays.value = data.settings.overdueDelayDays;
        welcomePreview.value = data.previews.welcome;
    } catch {
        loadError.value = 'Não foi possível carregar as preferências de mensagens.';
    } finally {
        loading.value = false;
    }
}

async function savePreferences() {
    if (saving.value || !hasChanges.value) return;
    saving.value = true;
    try {
        const { data } = await window.axios.patch('/api/organization/messaging/events', {
            events: preferences.value,
            overdueDelayDays: overdueDelayDays.value,
        });
        savedPreferences.value = data.events;
        preferences.value = { ...data.events };
        overdueDelayDays.value = savedOverdueDelayDays.value = data.overdueDelayDays;
        notify('Preferências de mensagens salvas.');
    } catch (error) {
        notify(error.response?.status === 403 ? 'Só gestores podem alterar essas preferências.' : 'Não foi possível salvar as preferências.', { type: 'error' });
    } finally {
        saving.value = false;
    }
}

onMounted(loadPreferences);
</script>

<style scoped>
.message-layout { display: grid; gap: 1.25rem; align-items: start; }
.message-preview { scroll-margin-top: 1rem; }
.message-phone {
    position: relative;
    width: min(100%, 16.5rem);
    aspect-ratio: 9 / 17.5;
    padding: 6px;
    border: 1px solid #43464c;
    border-radius: 2.4rem;
    background: linear-gradient(145deg, #393c41, #0d0e10 18%, #111214 80%, #34363b);
    box-shadow: inset 0 0 0 2px #232428, 0 14px 32px -15px #0f172a99;
}
.message-phone::before, .message-phone::after {
    position: absolute;
    width: 2px;
    border-radius: 2px;
    background: #393c41;
    content: '';
}
.message-phone::before { top: 17%; left: -3px; height: 5%; box-shadow: 0 2.25rem #393c41; }
.message-phone::after { top: 21%; right: -3px; height: 9%; }
.message-phone-screen {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    height: 100%;
    overflow: hidden;
    border-radius: 2rem;
    background: #f7f8fa;
}
.message-phone-status { position: relative; height: 2rem; }
.message-phone-camera {
    position: absolute;
    top: 5px;
    left: 50%;
    width: 27%;
    height: 15px;
    transform: translateX(-50%);
    border-radius: 999px;
    background: #0a0a0b;
}
.message-chat {
    background-color: #efeae2;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='112' height='104' viewBox='0 0 112 104'%3E%3Cg fill='none' stroke='%23d3c8b9' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round' opacity='.63'%3E%3Cpath d='M9 14h19a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H17l-7 5v-5H9a4 4 0 0 1-4-4v-8a4 4 0 0 1 4-4zM12 22h12m36-13 4 8 9 1-6 6 1 9-8-4-8 4 1-9-6-6 9-1zM86 49l18-10-5 19-5-7-8-2zm8 2 10-12M38 69c-9-6-2-15 4-9 6-6 13 3 4 9l-4 3zm25 13h18m-9-9v18M10 79a9 9 0 1 1 18 0 9 9 0 0 1-18 0zm5 2q4 4 8 0'/%3E%3Cpath d='M95 14h4m-2-2v4M52 45h4m-2-2v4m42 38h5m-2.5-2.5v5M7 53h4m-2-2v4'/%3E%3C/g%3E%3C/svg%3E");
}
.message-bubble { position: relative; }
.message-bubble::before {
    position: absolute;
    top: 0;
    left: -5px;
    width: 6px;
    height: 8px;
    background: white;
    clip-path: polygon(0 0, 100% 0, 100% 100%);
    content: '';
}

@media (min-width: 64rem) {
    .messages-page :deep(.dashboard-main) {
        display: flex;
        flex-direction: column;
        height: 100dvh;
        min-height: 0;
    }
    .messages-page :deep(.dashboard-content) {
        flex: 1;
        min-height: 0;
    }
    .messages-page :deep(.dashboard-content > .dashboard-container) {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .message-layout {
        flex: 1;
        min-height: 0;
        grid-template-columns: minmax(0, 1fr) minmax(17rem, 23rem);
        grid-template-rows: minmax(0, 1fr);
        align-items: stretch;
    }
    .message-list {
        overflow-y: auto;
        overscroll-behavior-y: contain;
        scrollbar-gutter: stable;
        padding-inline-end: .5rem;
    }
    .message-preview { container-type: size; }
    .message-phone { width: min(100%, 16.5rem, max(9rem, calc((100cqh - 4.5rem) * 9 / 17.5))); }
}
</style>
