<template><!-- Feedback is displayed by the global notification layer. --></template>
<script setup>
import { watch } from 'vue';
import { notify } from '../composables/useNotifications';
const props = defineProps({ modelValue: { type: String, default: '' }, message: { type: String, default: '' }, type: { type: String, default: 'success' }, action: Function, actionLabel: String });
const emit = defineEmits(['update:modelValue']);
watch(() => [props.modelValue, props.message], () => {
    const message = props.modelValue || props.message;
    if (!message) return;
    notify(message, { type: props.type, action: props.action, actionLabel: props.actionLabel });
    if (props.modelValue) emit('update:modelValue', '');
}, { immediate: true, flush: 'post' });
</script>
