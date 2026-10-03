<template>
    <section class="panel-card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Acesso do aluno</h2>
                <p class="mt-2 text-sm text-[var(--wn-muted)]">
                    Usuário e senha para acessar o portal e os treinos.
                </p>
            </div>
            <RouterLink to="/aluno/entrar" target="_blank" class="btn-secondary"
                ><ExternalLink :size="16" />Abrir portal</RouterLink
            >
        </div>
        <p v-if="loading" class="mt-5 text-sm">Carregando acesso…</p>
        <template v-else
            ><div v-if="access" class="mt-5 flex flex-wrap items-center gap-4">
                <strong class="rounded-xl bg-[var(--wn-surface-soft)] px-4 py-3"
                    >@{{ access.username }}</strong
                ><span
                    :class="
                        access.mustChangePassword
                            ? 'badge-warning'
                            : 'badge-success'
                    "
                    >{{
                        access.mustChangePassword
                            ? "Troca de senha pendente"
                            : "Acesso ativado"
                    }}</span
                ><span class="text-xs text-[var(--wn-muted)]">{{
                    access.lastLoginAt
                        ? `Último acesso: ${new Date(access.lastLoginAt).toLocaleString("pt-BR")}`
                        : "Ainda não entrou"
                }}</span>
            </div>
            <form
                v-else
                class="mt-5 flex flex-wrap items-end gap-3"
                @submit.prevent="issue(false)"
            >
                <label class="block text-sm font-medium"
                    >Usuário sugerido<input
                        v-model="username"
                        class="form-control mt-2"
                        pattern="[a-z0-9][a-z0-9._\-]+"
                        minlength="3"
                        maxlength="60"
                        required /></label
                ><button class="btn-primary" :disabled="busy">
                    Criar acesso
                </button>
            </form>
            <button
                v-if="access && !credentials"
                class="btn-secondary mt-5"
                :disabled="busy"
                @click="resetOpen = true"
            >
                <KeyRound :size="16" />Gerar nova senha provisória
            </button>
        </template>
        <div
            v-if="credentials"
            class="mt-5 rounded-xl border border-lime-200 bg-[var(--wn-primary-soft)] p-5"
        >
            <h3 class="font-semibold">Entregue estes dados ao aluno</h3>
            <p class="mt-2 text-sm leading-6">
                A senha aparece somente agora. O aluno precisará trocá-la ao
                entrar.
            </p>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-[var(--wn-muted)]">Usuário</dt>
                    <dd class="mt-1 font-mono font-bold">
                        {{ credentials.username }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--wn-muted)]">
                        Senha provisória
                    </dt>
                    <dd class="mt-1 font-mono font-bold">
                        {{ credentials.temporaryPassword }}
                    </dd>
                </div>
            </dl>
            <div class="mt-5 flex flex-wrap gap-3">
                <button class="btn-primary" @click="copy">
                    <Copy :size="16" />{{
                        copied ? "Copiado!" : "Copiar dados de acesso"
                    }}</button
                ><button class="btn-secondary" @click="dismiss">
                    Já entreguei ao aluno
                </button>
            </div>
        </div>
        <p v-if="error" class="student-error mt-4" role="alert">{{ error }}</p>
        <div
            v-if="resetOpen"
            class="student-modal-backdrop"
            @click.self="resetOpen = false"
        >
            <section
                class="student-card max-w-md"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reset-title"
            >
                <h2 id="reset-title" class="text-xl font-bold">
                    Gerar uma nova senha?
                </h2>
                <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                    A senha anterior deixará de funcionar e as sessões de acesso
                    serão encerradas. Entregue a nova senha provisória ao aluno.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        class="btn-primary"
                        :disabled="busy"
                        @click="issue(true)"
                    >
                        {{ busy ? "Gerando…" : "Gerar senha" }}</button
                    ><button
                        class="btn-secondary"
                        :disabled="busy"
                        @click="resetOpen = false"
                    >
                        Cancelar
                    </button>
                </div>
            </section>
        </div>
    </section>
</template>
<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import { Copy, ExternalLink, KeyRound } from "lucide-vue-next";
import { useStudentCredentialsStore } from "../stores/studentCredentials";
const props = defineProps({
    studentId: { type: Number, required: true },
    name: String,
});
const store = useStudentCredentialsStore();
const credentials = computed(() =>
    store.studentId === props.studentId ? store.credentials : null,
);
const access = ref(null);
const username = ref("");
const loading = ref(true);
const busy = ref(false);
const error = ref("");
const resetOpen = ref(false);
const copied = ref(false);
async function load() {
    loading.value = true;
    try {
        access.value = (
            await window.axios.get(`/api/students/${props.studentId}/access`)
        ).data.access;
        if (!access.value)
            username.value = (
                await window.axios.get("/api/student-access/suggest", {
                    params: { name: props.name },
                })
            ).data.username;
    } catch {
        error.value = "Não foi possível carregar o acesso.";
    } finally {
        loading.value = false;
    }
}
async function issue(reset) {
    busy.value = true;
    error.value = "";
    try {
        const { data } = await window.axios.post(
            `/api/students/${props.studentId}/access`,
            reset ? { reset: true } : { username: username.value },
        );
        store.set(props.studentId, data.credentials);
        copied.value = false;
        resetOpen.value = false;
        await load();
    } catch (e) {
        error.value =
            Object.values(e.response?.data?.errors ?? {}).flat()[0] ??
            e.response?.data?.message ??
            "Não foi possível gerar o acesso.";
    } finally {
        busy.value = false;
    }
}
async function copy() {
    try {
        await navigator.clipboard.writeText(
            `Portal do aluno: ${location.origin}/aluno/entrar\nUsuário: ${credentials.value.username}\nSenha provisória: ${credentials.value.temporaryPassword}\nTroque sua senha no primeiro acesso.`,
        );
        copied.value = true;
    } catch {
        error.value =
            "Não foi possível copiar. Selecione os dados e copie manualmente.";
    }
}
function dismiss() {
    store.clear();
}
onMounted(load);
</script>
