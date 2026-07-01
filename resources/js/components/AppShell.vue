<template>
    <div class="dashboard-shell min-h-screen bg-[var(--wn-canvas)]">
        <aside class="dashboard-sidebar hidden lg:flex">
            <div class="flex h-full flex-col px-5 py-6">
                <div class="px-2">
                    <AppLogo subtitle="" />
                </div>

                <nav class="mt-10 space-y-1.5">
                    <RouterLink
                        v-for="item in appNavigation"
                        :key="item.label"
                        :to="item.to"
                        class="sidebar-item"
                        :class="isActive(item) ? 'sidebar-item-active' : ''"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        <span>{{ item.label }}</span>
                    </RouterLink>
                </nav>

                <div class="mt-auto space-y-4">
                    <div class="flex items-center gap-3 rounded-[1.25rem] border border-[var(--wn-line)] bg-white px-4 py-4">
                        <div class="grid h-11 w-11 place-items-center rounded-xl bg-[var(--wn-neutral-soft)] font-semibold text-[var(--wn-neutral-strong)]">
                            {{ auth.initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[var(--wn-ink)]">{{ auth.user?.name }}</p>
                            <p class="truncate text-sm text-[var(--wn-muted)]">Administrador</p>
                        </div>
                    </div>

                    <button type="button" class="btn-secondary w-full justify-center" @click="handleLogout">
                        Sair
                    </button>
                </div>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-[292px]">
            <header class="dashboard-topbar">
                <div class="dashboard-container flex flex-col gap-4 px-4 py-4 sm:px-6 xl:px-8">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <button type="button" class="header-icon-button lg:hidden" aria-label="Sair" title="Sair" @click="handleLogout">
                                <LogOut class="h-5 w-5" />
                            </button>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[var(--wn-primary-strong)]">{{ eyebrow }}</p>
                                <h1 class="mt-1 text-2xl font-bold text-[var(--wn-ink)] sm:text-3xl">{{ title }}</h1>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--wn-muted)]">{{ description }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <label class="app-search">
                                <Search class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input :placeholder="searchPlaceholder" type="text" />
                                <span>⌘ K</span>
                            </label>

                            <button type="button" class="header-icon-button">
                                <Bell class="h-5 w-5" />
                                <span></span>
                            </button>

                            <button type="button" class="btn-secondary gap-2">
                                <CalendarDays class="h-5 w-5" />
                                Hoje
                                <ChevronDown class="h-4 w-4" />
                            </button>

                            <div class="header-user">
                                <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-neutral-soft)] font-semibold text-[var(--wn-ink)]">
                                    {{ auth.initials }}
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-sm font-semibold text-[var(--wn-ink)]">{{ auth.user?.name ?? 'Admin' }}</p>
                                    <p class="text-xs text-[var(--wn-muted)]">Administrador</p>
                                </div>
                                <ChevronDown class="h-4 w-4 text-[var(--wn-muted)]" />
                            </div>
                        </div>
                    </div>

                    <slot name="page-actions" />
                </div>
            </header>

            <main class="dashboard-content px-4 py-5 sm:px-6 xl:px-8">
                <div class="dashboard-container">
                    <slot />
                </div>
            </main>

            <nav class="mobile-tabbar lg:hidden">
                <RouterLink
                    v-for="item in mobileNavigation"
                    :key="item.label"
                    :to="item.to"
                    class="mobile-tabbar-item"
                    :class="isActive(item) ? 'mobile-tabbar-item-active' : ''"
                >
                    <component :is="item.icon" class="h-5 w-5" />
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { Bell, CalendarDays, ChevronDown, LogOut, Search } from 'lucide-vue-next';

import AppLogo from './AppLogo.vue';
import { appNavigation } from '../data/appNavigation';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
    eyebrow: {
        type: String,
        default: 'Painel',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: 'Buscar...',
    },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const currentPath = computed(() => route.path);
const mobileNavigation = computed(() => appNavigation.filter((item) => ['Painel', 'Alunos', 'Treinos', 'Agenda'].includes(item.label)));

const isActive = (item) => item.to !== '#' && currentPath.value.startsWith(item.to);

const handleLogout = async () => {
    await auth.logout();
    router.push('/entrar');
};
</script>
