import { ref } from 'vue';

export const notifications = ref([]);
let nextId = 0;
export function dismissNotification(id) {
    notifications.value = notifications.value.filter(item => item.id !== id);
}
export function notify(message, { type = 'success', duration, action, actionLabel = 'Tentar novamente' } = {}) {
    if (!message) return;
    const existing = notifications.value.find(item => item.message === message && item.type === type);
    if (existing) dismissNotification(existing.id);
    const id = ++nextId;
    notifications.value = [...notifications.value, { id, message, type, duration: duration ?? (type === 'error' ? 0 : 6000), action, actionLabel }].slice(-4);
    return id;
}
