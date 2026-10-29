<template>
    <Teleport :to="target"><section class="action-toast-region" aria-label="Avisos do sistema"><TransitionGroup name="action-toast"><ActionToast v-for="item in notifications" :key="item.id" :item="item" /></TransitionGroup></section></Teleport>
</template>
<script setup>
import { onMounted, onBeforeUnmount, shallowRef } from 'vue';
import ActionToast from './ActionToast.vue';
import { notifications } from '../composables/useNotifications';
const target = shallowRef('body');
let observer;
onMounted(() => {
    const updateTarget = () => { target.value = [...document.querySelectorAll('dialog[open]')].at(-1) || 'body'; };
    observer = new MutationObserver(updateTarget);
    observer.observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['open'] });
    updateTarget();
});
onBeforeUnmount(() => observer?.disconnect());
</script>
<style>
.action-toast-region { position: fixed; left: 50%; bottom: max(24px, env(safe-area-inset-bottom)); transform: translateX(-50%); z-index: 10000; display: flex; flex-direction: column; align-items: center; gap: 10px; width: min(520px, calc(100vw - 32px)); pointer-events: none; }
.action-toast { display: flex; align-items: flex-start; gap: 12px; width: 100%; padding: 16px; background: #fff; border: 1px solid #cbdcc6; border-left: 4px solid #54833c; border-radius: 14px; color: #20291e; box-shadow: 0 8px 32px #16221026; pointer-events: auto; }
.action-toast-error { border-color: #ecc6c6; border-left-color: #b54444; }
.action-toast-icon { flex-shrink: 0; color: #477a34; margin-top: 1px; }
.action-toast-error .action-toast-icon { color: #b54444; }
.action-toast-copy { flex: 1; min-width: 0; font-size: 13px; line-height: 1.6; overflow-wrap: anywhere; }
.action-toast-copy p { margin: 0; }
.action-toast-close { display: grid; place-items: center; flex-shrink: 0; width: 28px; height: 28px; margin: -3px -3px 0 0; border-radius: 7px; color: #626c5d; }
.action-toast-close:hover { background: #f0f3ed; }
.action-toast-close:focus-visible, .action-toast-retry:focus-visible { outline: 2px solid #54833c; outline-offset: 3px; }
.action-toast-retry { margin-top: 6px; font-weight: 650; text-decoration: underline; }
.action-toast-enter-active, .action-toast-leave-active { transition: opacity .18s, transform .18s; }
.action-toast-enter-from, .action-toast-leave-to { opacity: 0; transform: translateY(12px); }
@media (max-width: 1023px) { .action-toast-region { bottom: calc(88px + env(safe-area-inset-bottom)); } }
@media (prefers-reduced-motion: reduce) { .action-toast-enter-active, .action-toast-leave-active { transition: none; } }
</style>
