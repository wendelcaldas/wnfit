<template>
    <div class="auth-page">
        <main class="auth-layout">
            <AuthShowcase />

            <section class="auth-form-panel">
                <div class="auth-form-card auth-form-card-register">
                    <div class="flex items-center justify-between gap-4">
                        <div class="auth-wordmark auth-wordmark-small">
                            <div class="flex items-center gap-3">
                                <div class="brand-emblem">
                                    <span>W</span>
                                </div>
                                <p>WN<span>Fit</span></p>
                            </div>
                        </div>
                        <RouterLink to="/entrar" class="hidden text-sm font-medium text-[var(--wn-green)] transition hover:brightness-90 sm:inline-flex">
                            Ja tenho conta
                        </RouterLink>
                    </div>

                    <div class="space-y-1">
                        <h1 class="text-xl font-bold tracking-tight text-[var(--wn-ink)]">Criar conta</h1>
                        <p class="text-sm leading-6 text-[var(--wn-muted)]">
                            Preencha os dados iniciais para montar sua base WNFit.
                        </p>
                    </div>

                    <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Nome completo</span>
                            <div class="input-shell">
                                <UserRound class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.name" type="text" placeholder="Seu nome" class="auth-input" autocomplete="name" required />
                            </div>
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">E-mail</span>
                            <div class="input-shell">
                                <Mail class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.email" type="email" placeholder="E-mail" class="auth-input" autocomplete="email" required />
                            </div>
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Empresa ou studio</span>
                            <div class="input-shell">
                                <Building2 class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.company" type="text" placeholder="Organizacao" class="auth-input" autocomplete="organization" required />
                            </div>
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Tipo de negocio</span>
                            <select v-model="form.company_type" class="form-control" required>
                                <option value="" disabled>Selecione</option>
                                <option value="academia">Academia</option>
                                <option value="studio">Studio</option>
                                <option value="personal">Personal trainer</option>
                            </select>
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Telefone</span>
                            <div class="input-shell">
                                <Phone class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.phone" type="tel" placeholder="Telefone" class="auth-input" autocomplete="tel" />
                            </div>
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Senha (min. 8 caracteres)</span>
                            <div class="input-shell">
                                <LockKeyhole class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Senha segura" class="auth-input" autocomplete="new-password" minlength="8" required />
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

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Confirmar senha</span>
                            <div class="input-shell">
                                <ShieldCheck class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" placeholder="Repita a senha" class="auth-input" autocomplete="new-password" minlength="8" required />
                            </div>
                        </label>

                        <label class="md:col-span-2 flex items-start gap-3 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] px-4 py-3 text-sm leading-6 text-[var(--wn-muted)]">
                            <input v-model="acceptedTerms" type="checkbox" class="mt-1 h-4 w-4 rounded border-[var(--wn-line)] text-[var(--wn-green)] focus:ring-[var(--wn-green)]" />
                            <span>Li e aceito os termos de uso para criar o ambiente da minha empresa no WNFit.</span>
                        </label>

                        <p v-if="error" class="md:col-span-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-600">
                            {{ error }}
                        </p>

                        <div class="md:col-span-2 flex flex-col gap-3 pt-1 sm:flex-row">
                            <button type="submit" class="btn-primary w-full justify-center sm:w-auto sm:min-w-48" :disabled="loading || !acceptedTerms">
                                {{ loading ? 'Criando...' : 'Criar conta' }}
                            </button>
                            <RouterLink to="/entrar" class="btn-secondary w-full justify-center sm:w-auto sm:min-w-48">
                                Voltar para login
                            </RouterLink>
                        </div>
                    </form>
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
import { Building2, Eye, EyeOff, LockKeyhole, Mail, Phone, ShieldCheck, UserRound } from 'lucide-vue-next';

import AuthShowcase from '../components/AuthShowcase.vue';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const loading = ref(false);
const error = ref('');
const acceptedTerms = ref(false);
const showPassword = ref(false);

const form = reactive({
    name: '',
    email: '',
    company: '',
    company_type: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const submit = async () => {
    loading.value = true;
    error.value = '';

    try {
        await auth.register({
            ...form,
            terms: acceptedTerms.value,
        });
        router.replace('/painel');
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel criar a conta. Confira os dados e tente novamente.';
    } finally {
        loading.value = false;
    }
};
</script>
