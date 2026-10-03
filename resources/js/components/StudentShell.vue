<template>
    <div class="student-app">
        <header class="student-header">
            <RouterLink to="/aluno/inicio" class="flex items-center gap-3"
                ><span class="brand-emblem">W</span
                ><span
                    ><strong class="font-display text-xl">WNFit</strong
                    ><span class="block text-xs text-[var(--wn-muted)]">{{
                        auth.student?.organization
                    }}</span></span
                ></RouterLink
            >
            <div class="relative">
                <button
                    class="student-avatar"
                    aria-label="Abrir minha conta"
                    :aria-expanded="accountOpen"
                    @click="accountOpen = !accountOpen"
                >
                    {{ initials }}
                </button>
                <div
                    v-if="accountOpen"
                    class="absolute right-0 top-14 z-30 w-60 rounded-2xl border border-[var(--wn-line)] bg-white p-4 shadow-lg"
                >
                    <strong class="block text-sm">{{
                        auth.student?.name
                    }}</strong>
                    <p class="mt-1 text-xs text-[var(--wn-muted)]">
                        @{{ auth.student?.username }}
                    </p>
                    <RouterLink
                        to="/aluno/senha"
                        class="mt-4 block text-sm"
                        @click="accountOpen = false"
                        >Alterar senha</RouterLink
                    >
                    <button class="mt-4 text-sm text-rose-700" @click="logout">
                        Sair da conta
                    </button>
                </div>
            </div>
        </header>
        <main class="student-content"><slot /></main>
        <nav
            v-if="!hideNav"
            class="student-bottom-nav"
            aria-label="Menu do aluno"
        >
            <RouterLink
                to="/aluno/inicio"
                :class="{ selected: route.path === '/aluno/inicio' }"
                ><House :size="22" />Início</RouterLink
            >
            <RouterLink
                to="/aluno/treinos"
                :class="{
                    selected:
                        route.path.startsWith('/aluno/treino') ||
                        route.path.includes('/sessao'),
                }"
                ><Dumbbell :size="22" />Treinos</RouterLink
            >
        </nav>
    </div>
</template>
<script setup>
import { computed, ref } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { House, Dumbbell } from "lucide-vue-next";
import { useStudentAuthStore } from "../stores/studentAuth";
defineProps({ hideNav: Boolean });
const auth = useStudentAuthStore();
const router = useRouter();
const route = useRoute();
const accountOpen = ref(false);
const initials = computed(() =>
    auth.student?.name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((s) => s[0])
        .join(""),
);
async function logout() {
    await auth.logout();
    router.replace("/aluno/entrar");
}
</script>
