<template>
    <AppShell
        eyebrow="Configurações > Usuários"
        title="Usuários e professores"
        description="Gerencie quem acessa o sistema e quais professores ficam disponíveis no cadastro de alunos."
        search-placeholder="Buscar configurações..."
    >
        <SettingsNavigation />
        <section v-if="canManageContact" class="panel-card mb-5">
            <h2 class="text-lg font-semibold">WhatsApp do gestor</h2>
            <p class="mt-1 text-sm text-[var(--wn-muted)]">Telefone de contato informado no cadastro do ambiente. Os avisos por WhatsApp, quando habilitados, são enviados para este número.</p>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="saveContact">
                <label class="block space-y-2 sm:max-w-xs sm:flex-1">
                    <span class="text-sm font-medium text-[var(--wn-muted)]">Telefone com DDD</span>
                    <input v-model="contactPhone" class="form-control" type="tel" autocomplete="tel" placeholder="(71) 99999-9999" maxlength="30" />
                </label>
                <button class="btn-primary justify-center" :disabled="contactSaving">{{ contactSaving ? 'Salvando...' : 'Salvar telefone' }}</button>
            </form>
            <p class="mt-2 text-xs text-[var(--wn-muted)]">Este contato é compartilhado pelos gestores do ambiente. Deixe em branco para interromper os avisos por WhatsApp.</p>
            <ActionFeedback :message="contactError" type="error" />
        </section>
        <div class="grid gap-5 xl:grid-cols-[1fr_390px]">
            <section class="panel-card !p-0 overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-[var(--wn-line)] p-5">
                    <div>
                        <h2 class="text-lg font-semibold">Equipe cadastrada</h2>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">Professores ativos aparecem automaticamente no cadastro de aluno.</p>
                    </div>
                    <span class="badge-muted">{{ users.length }} usuários</span>
                </div>

                <div v-if="loading" class="p-8 text-center text-sm text-[var(--wn-muted)]">Carregando equipe...</div>
                <div v-else-if="!users.length" class="p-8 text-center text-sm text-[var(--wn-muted)]">Nenhum usuário cadastrado.</div>
                <div v-else class="divide-y divide-[var(--wn-line)]">
                    <article v-for="user in users" :key="user.id" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--wn-primary-soft)] font-semibold text-[var(--wn-primary-strong)]">
                            {{ user.initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ user.name }}</p>
                            <p class="truncate text-sm text-[var(--wn-muted)]">{{ user.email }}</p>
                        </div>
                        <select class="form-control sm:w-44" :value="user.role" @change="updateUser(user, { role: $event.target.value })">
                            <option value="proprietario" disabled>Proprietario</option>
                            <option value="administrador">Administrador</option>
                            <option value="professor">Professor</option>
                            <option value="atendimento">Atendimento</option>
                        </select>
                        <button
                            type="button"
                            class="min-w-24 rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="user.status === 'ativo' ? 'badge-success' : 'badge-muted'"
                            @click="updateUser(user, { status: user.status === 'ativo' ? 'inativo' : 'ativo' })"
                        >
                            {{ user.status === 'ativo' ? 'Ativo' : 'Inativo' }}
                        </button>
                    </article>
                </div>
            </section>

            <aside class="panel-card h-fit">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                        <UserPlus class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">Novo usuário</h2>
                        <p class="text-sm text-[var(--wn-muted)]">Cadastre um membro da equipe.</p>
                    </div>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="createUser">
                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Nome completo</span>
                        <input v-model="form.name" class="form-control" required />
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">E-mail</span>
                        <input v-model="form.email" class="form-control" type="email" required />
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Perfil</span>
                        <select v-model="form.role" class="form-control">
                            <option value="professor">Professor</option>
                            <option value="administrador">Administrador</option>
                            <option value="atendimento">Atendimento</option>
                        </select>
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Senha inicial</span>
                        <input v-model="form.password" class="form-control" type="password" minlength="8" required />
                    </label>
                    <ActionFeedback :message="error" type="error" />
                    <button class="btn-primary w-full justify-center" :disabled="saving">
                        {{ saving ? 'Salvando...' : 'Cadastrar usuário' }}
                    </button>
                </form>
            </aside>
        </div>
    </AppShell>
</template>

<script setup>
import SettingsNavigation from '../components/SettingsNavigation.vue';
import { notify } from '../composables/useNotifications';
import { onMounted, reactive, ref } from 'vue';
import { UserPlus } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import { useAuthStore } from '../stores/auth';

const users = ref([]);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const auth = useAuthStore();
const contactPhone = ref('');
const canManageContact = ref(false);
const contactSaving = ref(false);
const contactError = ref('');
const form = reactive({ name: '', email: '', role: 'professor', password: '' });

const loadUsers = async () => {
    const { data } = await window.axios.get('/api/organization/users');
    users.value = data.users;
    contactPhone.value = data.contact?.phone ?? '';
    canManageContact.value = data.canManageContact;
};

const saveContact = async () => {
    contactSaving.value = true;
    contactError.value = '';
    try {
        const { data } = await window.axios.patch('/api/organization/contact', { phone: contactPhone.value });
        contactPhone.value = data.contact.phone ?? '';
        if (auth.organization) auth.organization.phone = data.contact.phone;
        notify('Telefone do gestor atualizado.');
    } catch (exception) {
        contactError.value = Object.values(exception.response?.data?.errors ?? {}).flat()[0] ?? 'Não foi possível salvar o telefone.';
    } finally { contactSaving.value = false; }
};

onMounted(async () => {
    try { await loadUsers(); } finally { loading.value = false; }
});

const createUser = async () => {
    saving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post('/api/organization/users', form);
        users.value.push(data.user);
        users.value.sort((a, b) => a.name.localeCompare(b.name));
        Object.assign(form, { name: '', email: '', role: 'professor', password: '' });
        notify('Usuário cadastrado.');
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel cadastrar o usuário.';
    } finally { saving.value = false; }
};

const updateUser = async (user, changes) => {
    error.value = '';
    try {
        const { data } = await window.axios.patch(`/api/organization/users/${user.id}`, changes);
        Object.assign(user, data.user);
        notify('Usuário atualizado.');
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Nao foi possivel atualizar o usuário.';
        await loadUsers();
    }
};
</script>
