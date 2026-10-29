<template>
    <article class="action-toast" :class="'action-toast-' + item.type" :role="item.type === 'error' ? 'alert' : 'status'" aria-atomic="true" @mouseenter="pause" @mouseleave="resume" @focusin="pause" @focusout="resume">
        <component :is="item.type === 'error' ? CircleAlert : CircleCheck" class="action-toast-icon" :size="20" aria-hidden="true" />
        <div class="action-toast-copy"><p>{{ item.message }}</p><button v-if="item.action" class="action-toast-retry" type="button" @click="runAction">{{ item.actionLabel }}</button></div>
        <button class="action-toast-close" type="button" aria-label="Fechar aviso" @click="dismissNotification(item.id)"><X :size="18" /></button>
    </article>
</template>
<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { dismissNotification } from '../composables/useNotifications';
const props = defineProps({ item: { type: Object, required: true } });
let timer, startedAt, remaining = props.item.duration;
let hovering = false;
function pause(event) { if (event?.type === 'mouseenter') hovering = true; if (!timer) return; clearTimeout(timer); timer = null; remaining = Math.max(0, remaining - (Date.now() - startedAt)); }
function resume(event) {
    if (event?.type === 'mouseleave') hovering = false;
    if (!props.item.duration || timer || hovering || event?.currentTarget?.contains(document.activeElement)) return;
    startedAt = Date.now(); timer = setTimeout(() => dismissNotification(props.item.id), remaining);
}
function runAction() { const action = props.item.action; dismissNotification(props.item.id); action?.(); }
onMounted(() => resume());
onBeforeUnmount(() => clearTimeout(timer));
</script>
