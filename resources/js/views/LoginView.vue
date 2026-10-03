<template>
    <div class="student-login-page teacher-portal-login auth-login-ui">
            <section class="student-login-story">
                <div class="flex items-center gap-3">
                    <span class="brand-emblem">W</span>
                    <strong class="font-display text-2xl">WNFit</strong>
                </div>
                <div class="student-login-message">
                    <span class="student-overline">SEU ESPAÇO DE GESTÃO</span>
                    <h2 class="mt-5 font-display font-bold leading-tight">Mais tempo para transformar <span class="student-brand-accent">resultados.</span></h2>
                    <p class="mt-6 max-w-sm text-base leading-7 text-white/70">Organize seus alunos.<br />Acompanhe cada evolução.</p>
                </div>
                <p class="student-login-caption flex items-center gap-3 text-sm text-white/70">
                    <Dumbbell :size="20" /> Cada aluno. Um novo resultado.
                </p>
            </section>

            <main class="student-login-form">
                        <span class="student-overline">PORTAL DO PROFESSOR</span>
                        <h1 class="auth-login-title mt-3 font-display font-bold">Entrar na sua conta</h1>
                        <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">Bem-vindo de volta! Faça login para continuar.</p>

                    <form class="auth-login-form mt-8 space-y-4" :aria-busy="loading" @submit.prevent="submit">
                        <div class="auth-login-field">
                            <label for="teacher-email">E-mail</label>
                            <div class="input-shell">
                                <Mail class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input id="teacher-email" v-model="form.email" type="email" placeholder="Digite seu e-mail" class="auth-input" autocomplete="username" autocapitalize="none" spellcheck="false" required />
                            </div>
                        </div>

                        <div class="auth-login-field">
                            <label for="teacher-password">Senha</label>
                            <div class="input-shell">
                                <LockKeyhole class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input id="teacher-password" v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Digite sua senha" class="auth-input" autocomplete="current-password" required />
                                <button
                                    type="button"
                                    class="auth-password-toggle"
                                    :aria-pressed="showPassword"
                                    :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                    :title="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-5 w-5" />
                                    <Eye v-else class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center text-sm text-[var(--wn-muted)]">
                            <label class="flex items-center gap-2">
                                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[var(--wn-line)] text-[var(--wn-green)] focus:ring-[var(--wn-green)]" />
                                <span>Lembrar de mim</span>
                            </label>
                        </div>

                        <p v-if="error" role="alert" class="auth-login-error">
                            {{ error }}
                        </p>

                        <button type="submit" class="btn-primary w-full justify-center" :disabled="loading">
                            {{ loading ? 'Entrando…' : 'Entrar' }}<ArrowRight :size="18" />
                        </button>

                        <div class="teacher-login-divider flex items-center gap-4 py-1">
                            <div class="h-px flex-1 bg-[var(--wn-line)]"></div>
                            <span class="text-sm font-medium text-[var(--wn-muted)]">ou</span>
                            <div class="h-px flex-1 bg-[var(--wn-line)]"></div>
                        </div>

                        <RouterLink to="/cadastro" class="btn-secondary w-full justify-center gap-3">
                            <UserPlus class="h-5 w-5" />
                            Criar minha conta
                        </RouterLink>
                    </form>

                    <p class="mt-10 border-t border-[var(--wn-line)] pt-6 text-xs text-[var(--wn-muted)]">
                        <RouterLink to="/aluno/entrar" class="auth-support-link">Sou aluno — acessar meus treinos</RouterLink>
                    </p>
            </main>
    </div>
</template>

<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { reactive, ref } from 'vue';
import { ArrowRight, Dumbbell, Eye, EyeOff, LockKeyhole, Mail, UserPlus } from 'lucide-vue-next';

import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);

const form = reactive({
    email: '',
    password: '',
    remember: false,
});

const submit = async () => {
    loading.value = true;
    error.value = '';

    try {
        await auth.login(form);
        router.replace('/painel');
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors
            ? Object.values(errors).flat()[0]
            : 'Não foi possível entrar. Confira os dados e tente novamente.';
    } finally {
        loading.value = false;
    }
};
</script>
