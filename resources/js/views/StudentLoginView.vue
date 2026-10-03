<template>
    <div class="student-login-page auth-login-ui">
        <section class="student-login-story">
            <div class="flex items-center gap-3">
                <span class="brand-emblem">W</span
                ><strong class="font-display text-2xl">WNFit</strong>
            </div>
            <div class="student-login-message">
                <span class="student-overline">SEU ESPAÇO DE TREINO</span>
                <h1 class="mt-5 font-display font-bold leading-tight">
                    Seu próximo passo começa
                    <span class="student-brand-accent">aqui.</span>
                </h1>
                <p class="mt-6 max-w-sm text-base leading-7 text-white/70">
                    Disciplina hoje.<br />Resultados amanhã.
                </p>
            </div>
            <div class="student-login-caption flex gap-3 text-sm text-white/70">
                <Dumbbell :size="20" /> Um treino de cada vez. No seu ritmo.
            </div>
        </section>
        <main class="student-login-form">
            <span class="student-overline">PORTAL DO ALUNO</span>
            <h2 class="auth-login-title mt-3 font-display text-3xl font-bold">Vamos treinar?</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                Entre com o acesso enviado pelo seu professor.
            </p>
            <form class="auth-login-form mt-8 space-y-5" :aria-busy="busy" @submit.prevent="submit">
                <div class="auth-login-field">
                    <label for="student-username">Usuário</label>
                    <div class="input-shell">
                        <UserRound :size="20" class="text-[var(--wn-muted)]" />
                        <input
                        id="student-username"
                        v-model="form.username"
                        class="auth-input"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="Digite seu usuário"
                        required
                        maxlength="60"
                        />
                    </div>
                </div>
                <div class="auth-login-field">
                    <label for="student-password">Senha</label>
                    <div class="input-shell">
                        <LockKeyhole :size="20" class="text-[var(--wn-muted)]" />
                        <input
                            v-model="form.password"
                            :type="show ? 'text' : 'password'"
                            id="student-password"
                            class="auth-input"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                        /><button
                            class="auth-password-toggle"
                            type="button"
                            :aria-pressed="show"
                            :aria-label="
                                show ? 'Ocultar senha' : 'Mostrar senha'
                            "
                            @click="show = !show"
                        >
                            <EyeOff v-if="show" :size="20" /><Eye
                                v-else
                                :size="20"
                            />
                        </button>
                    </div>
                </div>
                <p v-if="error" role="alert" class="auth-login-error">
                    {{ error }}
                </p>
                <button
                    class="btn-primary w-full justify-center"
                    :disabled="busy"
                >
                    {{ busy ? "Entrando…" : "Entrar e treinar"
                    }}<ArrowRight :size="18" />
                </button>
            </form>
            <button class="student-login-help auth-support-link" type="button" :aria-expanded="help" aria-controls="student-login-help-content" @click="help = !help">
                Preciso de ajuda para entrar
            </button>
            <p
                v-if="help"
                id="student-login-help-content"
                class="mt-3 rounded-xl bg-[var(--wn-primary-soft)] p-4 text-sm leading-6"
            >
                Peça ao professor ou à recepção uma nova senha provisória. Você
                vai escolher sua nova senha ao entrar.
            </p>
            <p
                class="mt-10 border-t border-[var(--wn-line)] pt-6 text-xs text-[var(--wn-muted)]"
            >
                <RouterLink to="/entrar" class="student-staff-link auth-support-link"
                    ><UserRound :size="18" />Acesso do professor</RouterLink
                >
            </p>
        </main>
    </div>
</template>
<script setup>
import { reactive, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { ArrowRight, Dumbbell, Eye, EyeOff, LockKeyhole, UserRound } from "lucide-vue-next";
import { useStudentAuthStore } from "../stores/studentAuth";
import { useAuthStore } from "../stores/auth";
import { useStudentCredentialsStore } from "../stores/studentCredentials";
const auth = useStudentAuthStore();
const router = useRouter();
const form = reactive({ username: "", password: "" });
const show = ref(false);
const help = ref(false);
const busy = ref(false);
const error = ref("");
async function submit() {
    busy.value = true;
    error.value = "";
    try {
        await auth.login(form);
        useAuthStore().$reset();
        useStudentCredentialsStore().clear();
        router.replace(
            auth.student.mustChangePassword ? "/aluno/senha" : "/aluno/inicio",
        );
    } catch (e) {
        error.value =
            Object.values(e.response?.data?.errors ?? {}).flat()[0] ??
            "Não foi possível entrar. Tente novamente.";
    } finally {
        busy.value = false;
    }
}
</script>
