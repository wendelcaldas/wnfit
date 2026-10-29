<template>
    <AppShell :eyebrow="'Em breve'" :title="title" description="Estamos preparando este módulo para uma próxima etapa.">
        <section class="panel-card mx-auto flex max-w-xl flex-col items-center gap-4 px-6 py-12 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><Clock3 :size="26" aria-hidden="true" /></span>
            <span class="rounded-full bg-[var(--wn-surface-soft)] px-3 py-1 text-xs font-semibold text-[var(--wn-muted)]">Em breve</span>
            <h2 class="text-xl font-semibold">{{ title }} em preparação</h2>
            <p class="max-w-sm text-sm leading-6 text-[var(--wn-muted)]">Este módulo está temporariamente indisponível. Você pode continuar usando as demais funcionalidades do seu negócio.</p>
            <RouterLink to="/painel" class="btn-primary mt-2">Voltar ao painel</RouterLink>
        </section>
    </AppShell>
</template>
<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { Clock3 } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
const route = useRoute();
const title = computed(() => route.meta.moduleTitle || 'Módulo');
</script>
