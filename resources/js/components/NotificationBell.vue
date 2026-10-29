<template>
    <button
        ref="trigger"
        type="button"
        class="notification-trigger"
        :popovertarget="panelId"
        :aria-expanded="isOpen"
        :aria-controls="panelId"
        :aria-label="
            unreadCount
                ? 'Abrir notificações, ' + unreadCount + (unreadCount === 1 ? ' não lida' : ' não lidas')
                : 'Abrir notificações'
        "
    >
        <Bell :size="21" aria-hidden="true" />
        <span v-if="unreadCount" class="notification-badge" aria-hidden="true">{{
            unreadCount > 99 ? '99+' : unreadCount
        }}</span>
    </button>
    <section
        :id="panelId"
        ref="panel"
        popover="auto"
        class="notification-panel"
        role="dialog"
        :aria-labelledby="titleId"
        @beforetoggle="handleToggle"
    >
        <div class="notification-heading">
            <h2 :id="titleId">Notificações</h2>
            <button
                type="button"
                :popovertarget="panelId"
                popovertargetaction="hide"
                class="notification-close"
                aria-label="Fechar notificações"
            >
                <X :size="18" aria-hidden="true" />
            </button>
        </div>
        <div v-if="realtimeUnavailable && !isPreview" class="notification-preview-note" role="status">
            Atualização em tempo real indisponível.
            <button type="button" :disabled="loading" @click="loadNotifications()">Atualizar</button>
        </div>
        <div v-if="isPreview" class="notification-preview-note">Demonstração · dados ilustrativos</div>
        <div v-if="selected" class="notification-detail">
            <button type="button" class="notification-back" @click="backToList">
                <ArrowLeft :size="16" aria-hidden="true" />Voltar à lista
            </button>
            <span class="notification-kind">{{ kind(selected) }}</span>
            <h3 ref="detailHeading" tabindex="-1">
                {{ headline(selected) }}
            </h3>
            <template v-if="isBooking(selected)">
                <p>{{ selected.data.appointmentTitle }}</p>
                <p class="notification-completed-date">{{ date(selected.data.startsAt) }} – {{ time(selected.data.endsAt) }}</p>
                <dl>
                    <div><dt>Contato</dt><dd>{{ selected.data.phone }}</dd></div>
                    <div><dt>Professor</dt><dd>{{ selected.data.instructorName }}</dd></div>
                    <div v-if="selected.data.location"><dt>Local</dt><dd>{{ selected.data.location }}</dd></div>
                </dl>
            </template>
            <template v-else>
                <p>
                    {{ selected.data.workoutName }}<span v-if="selected.data.focus"> · {{ selected.data.focus }}</span>
                </p>
                <p class="notification-completed-date">{{ isDeadline(selected) ? 'Vigência até ' + planDate(selected.data.endsOn) : date(selected.data.completedAt) }}</p>
                <p v-if="isDeadline(selected)">Confira os treinos do aluno para renovar a vigência ou preparar uma nova versão da ficha.</p>
                <dl v-else>
                    <div>
                        <dt>Tempo decorrido</dt>
                        <dd>{{ duration(selected.data.durationSeconds) }}</dd>
                    </div>
                    <div>
                        <dt>Exercícios concluídos</dt>
                        <dd>{{ selected.data.completedExercises }} de {{ selected.data.totalExercises }}</dd>
                    </div>
                    <div>
                        <dt>Séries registradas</dt>
                        <dd>{{ selected.data.completedSets }} de {{ selected.data.totalSets }}</dd>
                    </div>
                </dl>
            </template>
            <small v-if="isPreview">Exemplo de resumo. Nenhum registro real foi alterado.</small>
            <a v-else :href="selected.data.href" class="btn-primary notification-student-link">{{ isBooking(selected) ? 'Ver agendamento' : 'Ver treinos do aluno' }}</a>
            <p v-if="readError" class="notification-error" role="alert">
                Não foi possível marcar como lida.
                <button type="button" :disabled="marking" @click="markRead(selected)">Tentar novamente</button>
            </p>
        </div>
        <template v-else>
            <div v-if="loadError" class="notification-error" role="alert">
                Não foi possível atualizar as notificações.
                <button type="button" :disabled="loading" @click="loadNotifications()">Tentar novamente</button>
            </div>
            <div v-if="loading && !loaded && !isPreview" class="notification-loading" role="status">
                Carregando notificações...
            </div>
            <div v-else-if="notifications.length" class="notification-list" aria-label="Lista de notificações">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="notification-row"
                    :class="{ 'is-unread': !notification.readAt }"
                    :aria-label="'Abrir ' + kind(notification).toLowerCase() + ' de ' + (isBooking(notification) ? notification.data.guestName : notification.data.studentName)"
                    @click="openNotification(notification)"
                >
                    <span class="notification-row-icon"><CalendarDays v-if="isBooking(notification)" :size="20" aria-hidden="true" /><Dumbbell v-else :size="20" aria-hidden="true" /></span>
                    <span class="notification-row-text"
                        ><strong>{{ kind(notification) }}</strong
                        ><span>{{ headline(notification) }}.</span
                        ><span v-if="isBooking(notification)">{{ notification.data.appointmentTitle }} · {{ date(notification.data.startsAt) }}</span
                        ><small
                            >{{ isPreview ? 'Há 5 minutos' : date(notification.createdAt) }} ·
                            {{ notification.readAt ? 'Lida' : 'Não lida' }}</small
                        ></span
                    >
                    <span v-if="!notification.readAt" class="notification-unread-dot" aria-hidden="true"></span>
                </button>
                <button
                    v-if="nextCursor && !isPreview"
                    type="button"
                    class="notification-load-more"
                    :disabled="loading"
                    @click="loadNotifications(true)"
                >
                    {{ loading ? 'Carregando...' : 'Carregar anteriores' }}
                </button>
            </div>
            <div v-else-if="!loadError" class="notification-empty">
                <span class="notification-empty-icon"><Bell :size="25" aria-hidden="true" /></span>
                <h3>Nenhuma notificação por enquanto</h3>
                <p>As novidades da sua operação aparecerão aqui.</p>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeft, Bell, CalendarDays, Dumbbell, X } from 'lucide-vue-next';
import { subscribeStaffNotifications } from '../composables/staffNotificationStream';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const isPreview = computed(() => import.meta.env.DEV && route.query.previewNotifications === '1');
const demo = ref({
    id: 'demo',
    readAt: null,
    createdAt: new Date().toISOString(),
    data: {
        studentName: 'Mariana',
        dayName: 'o treino A',
        workoutName: 'Treino de membros superiores',
        focus: '',
        completedAt: new Date().toISOString(),
        durationSeconds: 2280,
        completedExercises: 5,
        totalExercises: 5,
        completedSets: 15,
        totalSets: 15,
    },
});
const items = ref([]);
const count = ref(0);
const unreadCount = computed(() => (isPreview.value ? (demo.value.readAt ? 0 : 1) : count.value));
const notifications = computed(() => (isPreview.value ? [demo.value] : items.value));
const selected = ref(null);
const realtimeUnavailable = ref(false);
const detailHeading = ref(null);
const trigger = ref(null);
const panel = ref(null);
const isOpen = ref(false);
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
const readError = ref(false);
const marking = ref(false);
const nextCursor = ref(null);
const expandedHistory = ref(false);
const panelId = 'notifications-' + useId();
const titleId = panelId + '-title';
let requestVersion = 0,
    controller,
    stopStream,
    refreshTimer,
    pendingRefresh = false,
    destroyed = false;
const scope = computed(() => [auth.user?.id, auth.organization?.id, isPreview.value].join(':'));
const date = (value) =>
    new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(
        new Date(value),
    );
const isBooking = (notification) => notification?.type === 'public_booking_created';
const time = (value) => new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
const isDeadline = (notification) => ['workout_plan_ends_today', 'workout_plan_expired'].includes(notification?.type);
const planDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR');
const isDueToday = (notification) => notification.data.endsOn === new Date().toLocaleDateString('en-CA');
const kind = (notification) => isBooking(notification) ? 'Novo agendamento' : notification.type === 'workout_plan_expired'
    ? 'Ficha vencida'
    : notification.type === 'workout_plan_ends_today'
      ? (isDueToday(notification) ? 'Ficha vence hoje' : 'Vencimento da ficha')
      : 'Treino concluído';
const headline = (notification) => isBooking(notification) ? `${notification.data.guestName} agendou pelo link` : notification.type === 'workout_plan_expired'
    ? `A ficha de ${notification.data.studentName} venceu em ${planDate(notification.data.endsOn)}`
    : notification.type === 'workout_plan_ends_today'
      ? (isDueToday(notification) ? `A ficha de ${notification.data.studentName} vence hoje` : `A ficha de ${notification.data.studentName} tinha vigência até ${planDate(notification.data.endsOn)}`)
      : `${notification.data.studentName} finalizou ${notification.data.dayName}`;
const duration = (seconds) => {
    const minutes = Math.floor(Number(seconds || 0) / 60);
    return minutes < 1
        ? 'Menos de 1 min'
        : minutes >= 60
          ? Math.floor(minutes / 60) + ' h ' + (minutes % 60) + ' min'
          : minutes + ' minutos';
};
const loadNotifications = async (append = false) => {
    if (isPreview.value || !auth.user || !auth.organization || loading.value || marking.value || destroyed) return;
    const currentScope = scope.value;
    const version = ++requestVersion;
    controller = new AbortController();
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/notifications', {
            params: append && nextCursor.value ? { before: nextCursor.value } : {},
            signal: controller.signal,
        });
        if (destroyed || version !== requestVersion || currentScope !== scope.value) return;
        const incoming = Array.isArray(data.notifications) ? data.notifications : [];
        const refreshedSelection = incoming.find((item) => item.id === selected.value?.id);
        if (refreshedSelection) Object.assign(selected.value, refreshedSelection);
        items.value = append
            ? [...items.value, ...incoming.filter((n) => !items.value.some((old) => old.id === n.id))]
            : incoming;
        count.value = Number(data.unreadCount) || 0;
        nextCursor.value = data.nextCursor ?? null;
        expandedHistory.value = append;
        loaded.value = true;
        loadError.value = false;
    } catch (error) {
        if (destroyed || version !== requestVersion || error.code === 'ERR_CANCELED') return;
        loadError.value = true;
        if ([401, 403].includes(error.response?.status)) {
            items.value = [];
            count.value = 0;
            selected.value = null;
        }
    } finally {
        if (version === requestVersion) { loading.value = false; flushRefresh(); }
    }
};
const markRead = async (notification) => {
    if (notification.readAt || marking.value) return;
    readError.value = false;
    if (isPreview.value) {
        demo.value.readAt = new Date().toISOString();
        return;
    }
    controller?.abort();
    ++requestVersion;
    loading.value = false;
    const currentScope = scope.value;
    marking.value = true;
    try {
        const { data } = await window.axios.patch('/api/notifications/' + notification.id + '/read');
        if (destroyed || currentScope !== scope.value) return;
        Object.assign(notification, data.notification);
        const item = items.value.find((n) => n.id === notification.id);
        if (item) Object.assign(item, data.notification);
        count.value = Number(data.unreadCount) || 0;
    } catch {
        if (!destroyed && currentScope === scope.value) readError.value = true;
    } finally {
        marking.value = false;
        flushRefresh();
    }
};
const openNotification = async (notification) => {
    selected.value = notification;
    readError.value = false;
    await nextTick();
    detailHeading.value?.focus({ preventScroll: true });
    await markRead(notification);
};
const backToList = async () => {
    const id = selected.value?.id;
    selected.value = null;
    readError.value = false;
    await nextTick();
    const index = notifications.value.findIndex((n) => n.id === id);
    panel.value?.querySelectorAll('.notification-row')[index]?.focus({ preventScroll: true });
};
const handleToggle = (event) => {
    isOpen.value = event.newState === 'open';
    if (isOpen.value) {
        const bounds = trigger.value.getBoundingClientRect();
        panel.value.style.setProperty('--notification-top', bounds.bottom + 12 + 'px');
        const controls = trigger.value.parentElement.getBoundingClientRect();
        panel.value.style.setProperty('--notification-right', Math.max(16, window.innerWidth - controls.right) + 'px');
        if (!selected.value) void loadNotifications();
    } else {
        selected.value = null;
        readError.value = false;
        expandedHistory.value = false;
    }
};
// Fetch only after an event or subscription/reconnection, never on a timer loop.
const scheduleRefresh = () => {
    pendingRefresh = true;
    flushRefresh();
};
const flushRefresh = () => {
    if (!pendingRefresh || destroyed || loading.value || marking.value) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => {
        pendingRefresh = false;
        void loadNotifications();
    }, 150);
};
watch(
    scope,
    () => {
        stopStream?.();
        clearTimeout(refreshTimer);
        pendingRefresh = false;
        controller?.abort();
        ++requestVersion;
        loading.value = false;
        marking.value = false;
        items.value = [];
        count.value = 0;
        loaded.value = false;
        selected.value = null;
        nextCursor.value = null;
        loadError.value = false;
        if (!isPreview.value && auth.user && auth.organization) {
            stopStream = subscribeStaffNotifications({
                organizationId: auth.organization.id,
                userId: auth.user.id,
                onChange: scheduleRefresh,
                onSubscribed: () => { realtimeUnavailable.value = false; scheduleRefresh(); },
                onUnavailable: () => { realtimeUnavailable.value = true; },
            });
        }
        void loadNotifications();
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    destroyed = true;
    controller?.abort();
    clearTimeout(refreshTimer);
    stopStream?.();
});
</script>

<style scoped>
.notification-trigger,
.notification-close {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 10px;
    color: var(--wn-muted);
    background: transparent;
}
.notification-trigger:hover,
.notification-trigger[aria-expanded='true'],
.notification-close:hover {
    background: var(--wn-neutral-soft);
    color: var(--wn-ink);
}
.notification-trigger:focus-visible,
.notification-close:focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: 3px;
}
.notification-panel {
    position: fixed;
    top: var(--notification-top, 88px);
    right: var(--notification-right, 24px);
    bottom: auto;
    left: auto;
    margin: 0;
    padding: 0;
    width: min(360px, calc(100vw - 32px));
    max-height: calc(100dvh - var(--notification-top, 88px) - 16px);
    overflow-y: auto;
    border: 1px solid var(--wn-line);
    border-radius: 16px;
    background: white;
    color: var(--wn-ink);
    box-shadow: 0 16px 48px #1517151f;
}
.notification-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--wn-line);
    gap: 16px;
}
.notification-heading h2 {
    font-size: 16px;
    line-height: 1.4;
    font-weight: 600;
    margin: 0;
}
.notification-close {
    width: 36px;
    height: 36px;
}
.notification-empty {
    padding: 32px 24px 36px;
    text-align: center;
}
.notification-empty-icon {
    display: grid;
    place-items: center;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    margin: 0 auto 16px;
    background: var(--wn-surface-soft);
    color: var(--wn-muted);
}
.notification-empty h3 {
    font-size: 14px;
    line-height: 1.5;
    font-weight: 600;
    margin: 0;
}
.notification-empty p {
    font-size: 13px;
    line-height: 1.7;
    color: var(--wn-muted);
    margin: 8px 0 0;
}
@media (max-width: 639px) {
    .notification-panel {
        top: auto;
        bottom: max(16px, env(safe-area-inset-bottom));
        left: 16px;
        right: 16px;
        width: auto;
        max-height: 65dvh;
    }
}

.notification-trigger {
    position: relative;
}
.notification-badge {
    position: absolute;
    top: -1px;
    right: -1px;
    display: grid;
    place-items: center;
    min-width: 20px;
    height: 20px;
    padding-inline: 4px;
    border-radius: 10px;
    background: var(--wn-primary);
    color: var(--wn-ink);
    border: 2px solid var(--wn-canvas);
    font-size: 11px;
    font-weight: 700;
}
.notification-preview-note {
    padding: 8px 16px;
    font-size: 11px;
    color: var(--wn-muted);
    background: var(--wn-surface-soft);
    border-bottom: 1px solid var(--wn-line);
}
.notification-row {
    display: flex;
    align-items: start;
    gap: 12px;
    width: 100%;
    padding: 18px 16px;
    text-align: left;
}
.notification-row:hover {
    background: var(--wn-surface-soft);
}
.notification-row.is-unread {
    background: var(--wn-primary-soft);
}
.notification-row-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 10px;
    background: white;
    color: var(--wn-primary-strong);
}
.notification-row-text {
    display: grid;
    gap: 4px;
    flex: 1;
}
.notification-row-text strong {
    font-size: 13px;
    font-weight: 600;
}
.notification-row-text > span {
    font-size: 13px;
    line-height: 1.6;
}
.notification-row-text small {
    font-size: 11px;
    color: var(--wn-muted);
}
.notification-unread-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    margin-top: 6px;
    background: var(--wn-primary-strong);
    flex-shrink: 0;
}
.notification-detail {
    padding: 16px;
}
.notification-back {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    min-height: 40px;
    color: var(--wn-muted);
    margin-bottom: 12px;
}
.notification-kind {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: var(--wn-primary-strong);
}
.notification-detail h3 {
    font-size: 17px;
    line-height: 1.5;
    font-weight: 600;
    margin: 6px 0;
}
.notification-detail p {
    font-size: 13px;
    color: var(--wn-muted);
}
.notification-detail dl {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 16px 0;
}
.notification-detail dt {
    font-size: 11px;
    color: var(--wn-muted);
}
.notification-detail dd {
    font-size: 13px;
    font-weight: 600;
    margin-top: 4px;
}
.notification-detail > small {
    display: block;
    font-size: 11px;
    line-height: 1.6;
    color: var(--wn-muted);
}
.notification-row:focus-visible,
.notification-back:focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: -3px;
}
.notification-loading,
.notification-error {
    padding: 16px;
    font-size: 13px;
    line-height: 1.7;
    color: var(--wn-muted);
}
.notification-error button {
    text-decoration: underline;
    color: var(--wn-primary-strong);
    min-height: 36px;
}
.notification-load-more {
    display: block;
    min-height: 44px;
    padding: 12px 16px;
    width: 100%;
    font-size: 13px;
    border-top: 1px solid var(--wn-line);
    color: var(--wn-primary-strong);
}
.notification-student-link {
    display: flex;
    margin-top: 8px;
    justify-content: center;
}
.notification-detail .notification-completed-date {
    font-size: 12px;
    margin-top: 8px;
}
</style>
