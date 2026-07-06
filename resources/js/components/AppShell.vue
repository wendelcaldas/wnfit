<template>
    <div class="dashboard-shell min-h-screen bg-[var(--wn-canvas)]">
        <aside class="dashboard-sidebar hidden lg:flex"><div class="flex h-full flex-col px-5 py-6"><div class="px-2"><AppLogo subtitle="" /></div><nav class="mt-10 space-y-1.5"><RouterLink v-for="item in appNavigation" :key="item.label" :to="item.to" class="sidebar-item" :class="isActive(item) ? 'sidebar-item-active' : ''"><component :is="item.icon" class="h-5 w-5" /><span>{{ item.label }}</span></RouterLink></nav><div class="mt-auto space-y-4"><div class="flex items-center gap-3 rounded-[1.25rem] border border-[var(--wn-line)] bg-white px-4 py-4"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[var(--wn-neutral-soft)] font-semibold text-[var(--wn-neutral-strong)]">{{ auth.initials }}</div><div class="min-w-0"><p class="truncate text-sm font-semibold text-[var(--wn-ink)]">{{ auth.user?.name }}</p><p class="truncate text-sm text-[var(--wn-muted)]">Administrador</p></div></div><button type="button" class="btn-secondary w-full justify-center" @click="handleLogout">Sair</button></div></div></aside>

        <div class="min-h-screen lg:pl-[292px]">
            <header class="dashboard-topbar"><div class="dashboard-container px-4 py-3 sm:px-6 xl:px-8"><div class="flex items-start justify-between gap-4"><div class="min-w-0 flex-1"><p class="text-xs font-semibold text-[var(--wn-primary-strong)]">{{ eyebrow }}</p><h1 class="mt-0.5 truncate text-xl font-bold text-[var(--wn-ink)] sm:text-2xl">{{ title }}</h1><p v-if="description" class="mt-0.5 hidden max-w-2xl truncate text-sm text-[var(--wn-muted)] sm:block">{{ description }}</p></div>
                        <div class="flex shrink-0 items-start gap-3"><div v-if="$slots['page-actions']" class="hidden items-center gap-3 md:flex"><slot name="page-actions" /></div><details class="header-account relative"><summary class="header-account-trigger" aria-label="Abrir menu do usuario"><span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--wn-neutral-soft)] text-sm font-semibold text-[var(--wn-ink)]">{{ auth.initials }}</span><span class="hidden text-left lg:block"><strong class="block max-w-36 truncate text-sm text-[var(--wn-ink)]">{{ auth.user?.name ?? 'Admin' }}</strong><small class="block text-xs text-[var(--wn-muted)]">Administrador</small></span><ChevronDown class="hidden h-4 w-4 text-[var(--wn-muted)] sm:block" /></summary><div class="header-account-menu"><div class="border-b border-[var(--wn-line)] px-4 py-3 lg:hidden"><p class="truncate text-sm font-semibold">{{ auth.user?.name ?? 'Admin' }}</p><p class="text-xs text-[var(--wn-muted)]">Administrador</p></div><RouterLink to="/configuracoes/usuarios" class="header-account-item"><Settings class="h-4 w-4" />Configuracoes</RouterLink><button type="button" class="header-account-item w-full text-rose-600" @click="handleLogout"><LogOut class="h-4 w-4" />Sair</button></div></details></div></div><div v-if="$slots['page-actions']" class="mt-3 border-t border-[var(--wn-line)] pt-3 md:hidden"><slot name="page-actions" /></div></div></header>

            <main class="dashboard-content px-4 py-5 sm:px-6 xl:px-8"><div class="dashboard-container"><slot /></div></main>
            <nav class="mobile-tabbar lg:hidden"><RouterLink v-for="item in mobileNavigation" :key="item.label" :to="item.to" class="mobile-tabbar-item" :class="isActive(item) ? 'mobile-tabbar-item-active' : ''"><component :is="item.icon" class="h-5 w-5" /><span>{{ item.label }}</span></RouterLink></nav>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ChevronDown, LogOut, Settings } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { appNavigation } from '../data/appNavigation';
import { useAuthStore } from '../stores/auth';

defineProps({ eyebrow: { type: String, default: 'Painel' }, title: { type: String, required: true }, description: { type: String, default: '' }, searchPlaceholder: { type: String, default: '' } });
const route = useRoute(); const router = useRouter(); const auth = useAuthStore();
const currentPath = computed(() => route.path);
const mobileNavigation = computed(() => appNavigation.filter((item) => ['Painel', 'Alunos', 'Treinos', 'Agenda'].includes(item.label)));
const isActive = (item) => item.to !== '#' && currentPath.value.startsWith(item.to);
const handleLogout = async () => { await auth.logout(); router.push('/entrar'); };
</script>
