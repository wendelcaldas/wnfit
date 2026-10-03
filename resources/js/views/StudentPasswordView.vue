<template>
    <StudentShell
        class="student-password-page"
        :hide-nav="auth.student?.mustChangePassword"
    >
        <section
            class="student-card student-password-card mx-auto max-w-md !p-7"
        >
            <div class="student-icon"><KeyRound :size="24" /></div>
            <h1 class="mt-5 font-display text-2xl font-bold">
                {{
                    auth.student?.mustChangePassword
                        ? "Agora, uma senha só sua."
                        : "Alterar minha senha"
                }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                {{
                    auth.student?.mustChangePassword
                        ? "Antes de começar, substitua a senha provisória. Use pelo menos 8 caracteres."
                        : "Escolha uma nova senha com pelo menos 8 caracteres."
                }}
            </p>
            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <label class="student-field"
                    >{{
                        auth.student?.mustChangePassword
                            ? "Senha provisória"
                            : "Senha atual"
                    }}<input
                        v-model="form.current_password"
                        type="password"
                        class="form-control"
                        autocomplete="current-password"
                        required
                /></label>
                <label class="student-field"
                    >Nova senha<input
                        v-model="form.password"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        required
                /></label>
                <label class="student-field"
                    >Confirme a nova senha<input
                        v-model="form.password_confirmation"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        required
                /></label>
                <p v-if="error" class="student-error" role="alert">
                    {{ error }}
                </p>
                <button
                    class="btn-primary w-full justify-center"
                    :disabled="busy"
                >
                    {{ busy ? "Salvando…" : "Salvar nova senha" }}
                </button>
            </form>
        </section>
    </StudentShell>
</template>
<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { KeyRound } from "lucide-vue-next";
import StudentShell from "../components/StudentShell.vue";
import { useStudentAuthStore } from "../stores/studentAuth";
const auth = useStudentAuthStore();
const router = useRouter();
const busy = ref(false);
const error = ref("");
const form = reactive({
    current_password: "",
    password: "",
    password_confirmation: "",
});
async function submit() {
    busy.value = true;
    error.value = "";
    try {
        await auth.password(form);
        router.replace("/aluno/inicio");
    } catch (e) {
        error.value =
            Object.values(e.response?.data?.errors ?? {}).flat()[0] ??
            "Não foi possível salvar a senha.";
    } finally {
        busy.value = false;
    }
}
</script>
