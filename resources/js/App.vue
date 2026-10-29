<template>
    <RouterView />
    <NotificationToasts />
</template>

<script setup>
import { RouterView } from 'vue-router';
import NotificationToasts from './components/NotificationToasts.vue';
</script>
