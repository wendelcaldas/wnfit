<template>
    <div class="student-login-page">
        <section class="student-login-story">
            <div class="flex items-center gap-3">
                <span class="brand-emblem">W</span
                ><strong class="font-display text-2xl">WNFit</strong>
            </div>
            <div class="my-auto py-16">
                <span class="student-overline !text-lime-300"
                    >SEU ESPAÇO DE TREINO</span
                >
                <h1 class="mt-5 font-display text-5xl font-bold leading-tight">
                    Um treino de cada vez.<br /><span class="text-lime-300"
                        >Uma versão melhor de você.</span
                    >
                </h1>
                <p class="mt-6 max-w-sm text-base leading-7 text-slate-300">
                    Sua ficha, as orientações do professor e cada conquista.
                    Tudo no seu ritmo.
                </p>
            </div>
            <div class="flex gap-3 text-sm text-slate-300">
                <Dumbbell :size="20" /> Seu próximo passo começa aqui.
            </div>
        </section>
        <main class="student-login-form">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="brand-emblem">W</span
                ><strong class="font-display text-2xl">WNFit</strong>
            </div>
            <span class="student-overline">PORTAL DO ALUNO</span>
            <h2 class="mt-3 font-display text-3xl font-bold">
                Bom ter você aqui.
            </h2>
            <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                Entre com o usuário e a senha que sua academia entregou.
            </p>
            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <label class="student-field"
                    >Usuário<input
                        v-model="form.username"
                        class="form-control"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="Ex.: wendel"
                        required
                        maxlength="60"
                /></label>
                <label class="student-field"
                    >Senha
                    <div class="relative">
                        <input
                            v-model="form.password"
                            :type="show ? 'text' : 'password'"
                            class="form-control !pr-12"
                            autocomplete="current-password"
                            required
                        /><button
                            class="absolute right-3 top-3"
                            type="button"
                            :aria-label="
                                show ? 'Ocultar senha' : 'Mostrar senha'
                            "
                            @click="show = !show"
                        >
                            <EyeOff v-if="show" :size="20" /><Eye
                                v-else
                                :size="20"
                            />
                        </button></div
                ></label>
                <p v-if="error" role="alert" class="student-error">
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
            <button
                class="mt-6 text-sm font-semibold text-[var(--wn-primary-strong)]"
                @click="help = !help"
            >
                Esqueci meu usuário ou senha
            </button>
            <p
                v-if="help"
                class="mt-3 rounded-xl bg-[var(--wn-primary-soft)] p-4 text-sm leading-6"
            >
                Peça ao professor ou à recepção uma nova senha provisória. Você
                vai escolher sua nova senha ao entrar.
            </p>
            <p
                class="mt-10 border-t border-[var(--wn-line)] pt-6 text-xs text-[var(--wn-muted)]"
            >
                Faz parte da equipe?
                <RouterLink
                    to="/entrar"
                    class="font-semibold text-[var(--wn-ink)]"
                    >Acessar gestão</RouterLink
                >
            </p>
        </main>
    </div>
</template>
<script setup>
import { reactive, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { ArrowRight, Dumbbell, Eye, EyeOff } from "lucide-vue-next";
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
