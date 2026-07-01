<template>
    <div class="auth-page">
        <main class="auth-layout">
            <AuthShowcase />

            <section class="auth-form-panel">
                <div class="auth-form-card auth-form-card-login">
                    <div class="auth-wordmark">
                        <div class="flex items-center justify-center gap-3">
                            <div class="brand-emblem">
                                <span>W</span>
                            </div>
                            <p>WN<span>Fit</span></p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h1 class="text-2xl font-bold text-[var(--wn-ink)]">Entrar na sua conta</h1>
                        <p class="text-sm leading-6 text-[var(--wn-muted)]">Bem-vindo de volta! Faca login para continuar.</p>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <label class="block space-y-2">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">E-mail</span>
                            <div class="input-shell">
                                <Mail class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.email" type="email" placeholder="Digite seu e-mail" class="auth-input" autocomplete="email" required />
                            </div>
                        </label>

                        <label class="block space-y-2">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Senha</span>
                            <div class="input-shell">
                                <LockKeyhole class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Digite sua senha" class="auth-input" autocomplete="current-password" required />
                                <button
                                    type="button"
                                    class="text-[var(--wn-muted)] transition hover:text-[var(--wn-ink)]"
                                    :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                    :title="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-5 w-5" />
                                    <Eye v-else class="h-5 w-5" />
                                </button>
                            </div>
                        </label>

                        <div class="flex items-center text-sm text-[var(--wn-muted)]">
                            <label class="flex items-center gap-2">
                                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[var(--wn-line)] text-[var(--wn-green)] focus:ring-[var(--wn-green)]" />
                                <span>Lembrar de mim</span>
                            </label>
                        </div>

                        <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-600">
                            {{ error }}
                        </p>

                        <button type="submit" class="btn-primary w-full justify-center" :disabled="loading">
                            {{ loading ? 'Entrando...' : 'Entrar' }}
                        </button>

                        <div class="flex items-center gap-4 py-1">
                            <div class="h-px flex-1 bg-[var(--wn-line)]"></div>
                            <span class="text-sm font-medium text-[var(--wn-muted)]">ou</span>
                            <div class="h-px flex-1 bg-[var(--wn-line)]"></div>
                        </div>

                        <RouterLink to="/cadastro" class="btn-secondary w-full justify-center gap-3">
                            <UserPlus class="h-5 w-5" />
                            Criar minha conta
                        </RouterLink>
                    </form>

                    <p class="text-center text-sm text-[var(--wn-muted)]">
                        Ainda nao tem uma conta?
                        <RouterLink to="/cadastro" class="font-medium text-[var(--wn-green)] transition hover:brightness-90">
                            Criar conta
                        </RouterLink>
                    </p>
                </div>
            </section>

            <footer class="auth-footer">
                <span>WNFit</span> &copy; 2026 &bull; Todos os direitos reservados.
            </footer>
        </main>
    </div>
</template>

<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { reactive, ref } from 'vue';
import { Eye, EyeOff, LockKeyhole, Mail, UserPlus } from 'lucide-vue-next';

import AuthShowcase from '../components/AuthShowcase.vue';
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
            : 'Nao foi possivel entrar. Confira os dados e tente novamente.';
    } finally {
        loading.value = false;
    }
};
</script>
