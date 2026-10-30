<template>
    <AppShell eyebrow="Configurações > Usuários" title="Usuários e professores" description="Gerencie os integrantes e os contatos da sua equipe.">
        <SettingsNavigation />
        <ActionFeedback :message="error" type="error" />
        <div class="grid gap-5 2xl:grid-cols-[1fr_340px]">
            <section class="panel-card !p-0 overflow-hidden h-fit">
                <div class="flex items-center justify-between gap-4 border-b border-[var(--wn-line)] p-5">
                    <div><h2 class="text-lg font-semibold">Equipe cadastrada</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Contatos, funções e acesso ao ambiente.</p></div>
                    <span class="badge-muted">{{ users.length }} usuários</span>
                </div>
                <div v-if="loading" class="p-8 text-center text-sm text-[var(--wn-muted)]">Carregando equipe...</div>
                <div v-else-if="!users.length" class="p-8 text-center text-sm text-[var(--wn-muted)]">Nenhum usuário cadastrado.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[var(--wn-neutral-soft)] text-[var(--wn-muted)]"><tr><th class="px-5 py-3 font-medium">Nome</th><th class="px-4 py-3 font-medium">Função</th><th class="px-4 py-3 font-medium">Telefone</th><th class="px-4 py-3 font-medium">Status</th><th class="px-5 py-3 text-right font-medium">Ações</th></tr></thead>
                        <tbody class="divide-y divide-[var(--wn-line)]">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--wn-primary-soft)] font-semibold text-[var(--wn-primary-strong)]" aria-hidden="true">{{ user.initials }}</span><div><p class="font-semibold whitespace-nowrap">{{ user.name }}</p><p class="text-xs text-[var(--wn-muted)]">{{ user.email }}</p></div></div></td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ roleLabel(user.role) }}</td>
                                <td class="px-4 py-4"><button type="button" class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap text-[var(--wn-muted)] hover:text-[var(--wn-ink)] disabled:cursor-default" :disabled="!canManage" :aria-label="'Editar telefone de ' + user.name" @click="openEdit(user, true)"><Smartphone class="h-4 w-4 shrink-0" aria-hidden="true" />{{ formatPhone(user.phone) }}</button></td>
                                <td class="px-4 py-4"><span :class="user.status === 'ativo' ? 'badge-success' : 'badge-muted'">{{ user.status === 'ativo' ? 'Ativo' : 'Inativo' }}</span></td>
                                <td class="px-5 py-4"><div class="flex justify-end gap-1"><button type="button" class="team-icon" :disabled="!canManage" :aria-label="'Editar ' + user.name" title="Editar usuário" @click="openEdit(user)"><Pencil class="h-4 w-4" aria-hidden="true" /></button><button type="button" class="team-icon text-rose-600" :disabled="!canManage || user.role === 'proprietario' || user.id === auth.user?.id" :aria-label="'Excluir ' + user.name" :title="user.role === 'proprietario' || user.id === auth.user?.id ? 'O proprietário e o próprio acesso são preservados' : 'Excluir da equipe'" @click="openDelete(user)"><Trash2 class="h-4 w-4" aria-hidden="true" /></button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <aside v-if="canManage" class="panel-card h-fit">
                <div class="flex items-center gap-3"><UserPlus class="h-5 w-5 text-[var(--wn-primary-strong)]" aria-hidden="true" /><h2 class="text-lg font-semibold">Novo usuário</h2></div>
                <form class="mt-5 space-y-4" @submit.prevent="createUser">
                    <label class="block space-y-2"><span class="text-sm font-medium">Nome completo</span><input v-model="form.name" class="form-control" required maxlength="255" /></label>
                    <label class="block space-y-2"><span class="text-sm font-medium">E-mail</span><input v-model="form.email" class="form-control" type="email" required maxlength="255" /></label>
                    <label class="block space-y-2"><span class="text-sm font-medium">Telefone com DDD</span><input v-model="form.phone" class="form-control" type="tel" maxlength="30" placeholder="(71) 99999-9999" /></label>
                    <label class="block space-y-2"><span class="text-sm font-medium">Função</span><select v-model="form.role" class="form-control"><option value="professor">Professor</option><option value="administrador">Administrador</option><option value="atendimento">Atendimento</option></select></label>
                    <label class="block space-y-2"><span class="text-sm font-medium">Senha inicial</span><input v-model="form.password" class="form-control" type="password" minlength="8" required autocomplete="new-password" /></label>
                    <button class="btn-primary w-full justify-center" :disabled="saving">{{ saving ? 'Salvando...' : 'Cadastrar usuário' }}</button>
                </form>
            </aside>
        </div>
        <dialog ref="editDialog" class="team-dialog" aria-labelledby="edit-team-title" @cancel="preventBusyClose" @close="editing = null">
            <form v-if="editing" class="space-y-4" @submit.prevent="saveEdit">
                <h2 id="edit-team-title" class="text-lg font-semibold">Editar integrante</h2>
                <label class="block space-y-2"><span class="text-sm font-medium">Nome completo</span><input v-model="editForm.name" class="form-control" required maxlength="255" /></label>
                <label class="block space-y-2"><span class="text-sm font-medium">E-mail</span><input v-model="editForm.email" class="form-control" type="email" required maxlength="255" /></label>
                <label class="block space-y-2"><span class="text-sm font-medium">Telefone com DDD</span><input ref="phoneInput" v-model="editForm.phone" class="form-control" type="tel" maxlength="30" placeholder="(71) 99999-9999" /></label>
                <p v-if="editing.role === 'proprietario'" class="text-xs text-[var(--wn-muted)]">Este telefone recebe os avisos de treino concluído quando o WhatsApp está habilitado para o ambiente.</p>
                <label class="block space-y-2"><span class="text-sm font-medium">Função</span><select v-model="editForm.role" class="form-control" :disabled="editing.role === 'proprietario'"><option value="proprietario" disabled>Proprietário</option><option value="administrador">Administrador</option><option value="professor">Professor</option><option value="atendimento">Atendimento</option></select></label>
                <label class="block space-y-2"><span class="text-sm font-medium">Status</span><select v-model="editForm.status" class="form-control" :disabled="editing.id === auth.user?.id"><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select></label>
                <ActionFeedback :message="dialogError" type="error" />
                <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" :disabled="dialogBusy" @click="editDialog.close()">Cancelar</button><button class="btn-primary" :disabled="dialogBusy">{{ dialogBusy ? 'Salvando...' : 'Salvar alterações' }}</button></div>
            </form>
        </dialog>
        <dialog ref="deleteDialog" class="team-dialog" aria-labelledby="delete-team-title" @cancel="preventBusyClose" @close="deleting = null">
            <form v-if="deleting" class="space-y-4" @submit.prevent="deleteUser">
                <h2 id="delete-team-title" class="text-lg font-semibold">Excluir da equipe</h2>
                <p class="text-sm leading-6">Excluir <strong>{{ deleting.name }}</strong> da equipe? Essa pessoa perderá o acesso a este ambiente. O acesso a outros ambientes será preservado.</p>
                <ActionFeedback :message="dialogError" type="error" />
                <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" autofocus :disabled="dialogBusy" @click="deleteDialog.close()">Cancelar</button><button class="btn-primary !bg-rose-700" :disabled="dialogBusy">{{ dialogBusy ? 'Excluindo...' : 'Excluir da equipe' }}</button></div>
            </form>
        </dialog>
    </AppShell>
</template>
<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import { Pencil, Smartphone, Trash2, UserPlus } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import SettingsNavigation from '../components/SettingsNavigation.vue';
import { notify } from '../composables/useNotifications';
import { useAuthStore } from '../stores/auth';
const auth = useAuthStore();
const users = ref([]), loading = ref(true), saving = ref(false), error = ref(''), canManage = ref(false);
const emptyForm = () => ({ name: '', email: '', phone: '', role: 'professor', password: '' });
const form = reactive(emptyForm());
const editing = ref(null), deleting = ref(null), editDialog = ref(null), deleteDialog = ref(null), phoneInput = ref(null), dialogBusy = ref(false), dialogError = ref('');
const editForm = reactive({ name: '', email: '', phone: '', role: '', status: '' });
const message = e => Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? e.response?.data?.message ?? 'Não foi possível concluir a ação.';
const roleLabel = role => ({ proprietario: 'Proprietário', administrador: 'Administrador', professor: 'Professor', atendimento: 'Atendimento' })[role] ?? role;
function formatPhone(value) { const digits = (value ?? '').replace(/\D/g, '').replace(/^(55)(?=\d{10,11}$)/, ''); return digits.replace(/^(\d{2})(\d{4,5})(\d{4})$/, '($1) $2-$3') || 'Não informado'; }
async function loadUsers() { const { data } = await window.axios.get('/api/organization/users'); users.value = data.users; canManage.value = data.canManage; }
onMounted(async () => { try { await loadUsers(); } catch (e) { error.value = message(e); } finally { loading.value = false; } });
async function createUser() { saving.value = true; error.value = ''; try { const { data } = await window.axios.post('/api/organization/users', form); users.value.push(data.user); users.value.sort((a, b) => a.name.localeCompare(b.name)); Object.assign(form, emptyForm()); notify('Usuário cadastrado.'); } catch (e) { error.value = message(e); } finally { saving.value = false; } }
async function openEdit(user, focusPhone = false) { editing.value = user; dialogError.value = ''; Object.assign(editForm, { name: user.name, email: user.email, phone: user.phone ?? '', role: user.role, status: user.status }); await nextTick(); editDialog.value.showModal(); if (focusPhone) phoneInput.value?.focus(); }
async function saveEdit() { if (dialogBusy.value) return; dialogBusy.value = true; dialogError.value = ''; try { const payload = { ...editForm }; if (editing.value.role === 'proprietario') delete payload.role; const { data } = await window.axios.patch(`/api/organization/users/${editing.value.id}`, payload); Object.assign(editing.value, data.user); if (data.user.id === auth.user?.id) Object.assign(auth.user, { name: data.user.name, email: data.user.email }); if (data.user.role === 'proprietario' && auth.organization) auth.organization.phone = data.user.phone; editDialog.value.close(); notify('Integrante atualizado.'); } catch (e) { dialogError.value = message(e); } finally { dialogBusy.value = false; } }
async function openDelete(user) { deleting.value = user; dialogError.value = ''; await nextTick(); deleteDialog.value.showModal(); }
async function deleteUser() { if (dialogBusy.value) return; dialogBusy.value = true; dialogError.value = ''; try { const id = deleting.value.id; await window.axios.delete(`/api/organization/users/${id}`); users.value = users.value.filter(user => user.id !== id); deleteDialog.value.close(); notify('Integrante excluído da equipe.'); } catch (e) { dialogError.value = message(e); } finally { dialogBusy.value = false; } }
function preventBusyClose(event) { if (dialogBusy.value) event.preventDefault(); }
</script>
<style scoped>
.team-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px; cursor: pointer; }
.team-icon:hover:not(:disabled) { background: var(--wn-neutral-soft); }
.team-icon:disabled { opacity: .35; cursor: not-allowed; }
.team-icon:focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: 2px; }
.team-dialog { margin: auto; padding: 24px; border: 1px solid var(--wn-line); border-radius: 20px; width: min(480px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; color: var(--wn-ink); background: white; }
.team-dialog::backdrop { background: rgb(15 23 42 / .4); }
</style>
