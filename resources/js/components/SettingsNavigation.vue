<template>
    <nav aria-label="Configurações" class="mb-5 flex flex-wrap gap-2 border-b border-[var(--wn-line)] pb-3">
        <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="rounded-xl px-4 py-2 text-sm font-semibold" :class="route.path === item.to ? 'bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]' : 'text-[var(--wn-muted)] hover:bg-[var(--wn-surface-soft)]'" :aria-current="route.path === item.to ? 'page' : undefined"><component :is="item.icon" class="mr-2 inline h-4 w-4" />{{ item.label }}</RouterLink>
    </nav>
</template>
<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { Users, Tags, MapPin } from 'lucide-vue-next';
const route = useRoute();
const items = [{ label: 'Usuários', to: '/configuracoes/usuarios', icon: Users }, { label: 'Planos', to: '/configuracoes/planos', icon: Tags }, { label: 'Unidades', to: '/configuracoes/unidades', icon: MapPin }];
</script>
