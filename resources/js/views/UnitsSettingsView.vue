<template>
    <AppShell eyebrow="Configurações > Unidades" title="Unidades" description="Organize as unidades da sua academia, estúdio ou atendimento personal.">
        <SettingsNavigation />
        <p v-if="notice" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ notice }}</p>
        <p v-if="loadError" role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{{ loadError }} <button class="underline" @click="load">Tentar novamente</button></p>
        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="panel-card !p-0 overflow-hidden" aria-labelledby="units-title">
                <div class="flex items-center justify-between gap-3 border-b border-[var(--wn-line)] p-5"><div><h2 id="units-title" class="text-lg font-semibold">Unidades do seu negócio</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">A unidade padrão vem selecionada no cadastro de alunos.</p></div><button v-if="canManage" class="btn-primary whitespace-nowrap" :disabled="saving" @click="create"><Plus :size="16" />Nova unidade</button></div>
                <p v-if="loading" class="p-8 text-center text-sm text-[var(--wn-muted)]">Carregando unidades…</p>
                <div v-else class="divide-y divide-[var(--wn-line)]"><article v-for="unit in units" :key="unit.id" class="flex flex-wrap items-center gap-4 p-5" :class="{ 'bg-[var(--wn-primary-soft)]': editingId === unit.id }"><span class="grid h-10 w-10 place-items-center rounded-xl bg-[var(--wn-surface-soft)] text-[var(--wn-primary-strong)]"><MapPin :size="19" /></span><div class="min-w-0 flex-1"><h3 class="break-words font-semibold">{{ unit.name }}</h3><p class="mt-1 text-xs text-[var(--wn-muted)]">{{ unit.students }} {{ unit.students === 1 ? 'aluno vinculado' : 'alunos vinculados' }}</p></div><span v-if="unit.default" class="badge-warning">Padrão</span><span :class="unit.active ? 'badge-success' : 'badge-muted'">{{ unit.active ? 'Ativa' : 'Inativa' }}</span><button v-if="canManage" class="btn-secondary" :disabled="saving" :aria-label="'Editar unidade ' + unit.name" @click="edit(unit)"><Pencil :size="15" />Editar</button><button v-if="canManage" class="btn-secondary !text-rose-700" :disabled="saving || unit.default" :aria-label="'Excluir unidade ' + unit.name" :title="unit.default ? 'Defina outra unidade como padrão antes de excluir' : 'Excluir unidade'" @click="deletingUnit = unit"><Trash2 :size="15" />Excluir</button></article></div>
            </section>
            <aside v-if="canManage" ref="editor" class="panel-card" aria-labelledby="unit-editor-title">
                <h2 id="unit-editor-title" class="text-lg font-semibold">{{ editingId ? 'Editar unidade' : 'Nova unidade' }}</h2>
                <form class="mt-5 grid gap-4" @submit.prevent="save">
                    <label class="grid gap-2 text-sm">Nome da unidade<input ref="nameInput" v-model="form.name" class="form-control" required maxlength="255" placeholder="Ex.: Studio Centro" :disabled="saving" /></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.active" type="checkbox" :disabled="saving || isCurrentDefault" />Disponível para novos alunos</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.default" type="checkbox" :disabled="saving || isCurrentDefault || !form.active" />Usar como unidade padrão</label>
                    <p v-if="isCurrentDefault" class="text-xs leading-5 text-[var(--wn-muted)]">Para desativar esta unidade, defina outra como padrão primeiro.</p>
                    <p class="rounded-xl bg-[var(--wn-surface-soft)] p-3 text-xs leading-5 text-[var(--wn-muted)]">Renomear atualiza a identificação dos alunos vinculados. Desativar retira a unidade das opções de novos cadastros, preservando os alunos atuais. Alterar a unidade padrão não transfere alunos entre unidades.</p>
                    <p v-if="error" role="alert" class="text-sm text-rose-700">{{ error }}</p>
                    <div class="flex gap-2"><button class="btn-primary flex-1 justify-center" :disabled="saving || loading">{{ saving ? 'Salvando…' : editingId ? 'Salvar alterações' : 'Criar unidade' }}</button><button v-if="editingId" type="button" class="btn-secondary" :disabled="saving" @click="create">Cancelar</button></div>
                </form>
            </aside>
            <aside v-else-if="!loading && !loadError" class="panel-card text-sm text-[var(--wn-muted)]">A criação e edição de unidades estão disponíveis ao proprietário e aos administradores.</aside>
        </div>
        <UnitDeleteDialog v-if="deletingUnit" :unit="deletingUnit" :units="units" @close="deletingUnit = null" @deleted="deleted" />
    </AppShell>
</template>
<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Plus, MapPin, Pencil, Trash2 } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import SettingsNavigation from '../components/SettingsNavigation.vue';
import UnitDeleteDialog from '../components/UnitDeleteDialog.vue';
const deletingUnit = ref(null);
const units = ref([]), loading = ref(true), saving = ref(false), canManage = ref(false), editingId = ref(null), error = ref(''), loadError = ref(''), notice = ref('');
const editor = ref(null), nameInput = ref(null);
const form = reactive({ name: '', active: true, default: false });
const isCurrentDefault = computed(() => units.value.find(unit => unit.id === editingId.value)?.default ?? false);
watch(() => form.active, active => { if (!active) form.default = false; });
async function load() {
    loading.value = true; loadError.value = '';
    try { const { data } = await window.axios.get('/api/organization/units'); units.value = data.units; canManage.value = data.canManage; }
    catch { loadError.value = 'Não foi possível carregar as unidades.'; }
    finally { loading.value = false; }
}
async function focusEditor() { await nextTick(); nameInput.value?.focus({ preventScroll: true }); editor.value?.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); }
function reset() { editingId.value = null; Object.assign(form, { name: '', active: true, default: false }); error.value = ''; }
function create() { reset(); focusEditor(); }
function edit(unit) { editingId.value = unit.id; Object.assign(form, { name: unit.name, active: unit.active, default: unit.default }); error.value = ''; focusEditor(); }
async function save() {
    if (saving.value) return;
    saving.value = true; error.value = ''; notice.value = '';
    try {
        if (editingId.value) await window.axios.put('/api/organization/units/' + editingId.value, { ...form });
        else await window.axios.post('/api/organization/units', { ...form });
        notice.value = editingId.value ? 'Unidade atualizada.' : 'Unidade criada e disponível no cadastro de alunos.';
        reset(); await load();
    } catch (e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] || e.response?.data?.message || 'Não foi possível salvar a unidade.'; }
    finally { saving.value = false; }
}
async function deleted(count) { const id = deletingUnit.value?.id; deletingUnit.value = null; if (editingId.value === id) reset(); notice.value = count ? 'Unidade excluída. ' + count + ' aluno(s) transferido(s) para a unidade escolhida.' : 'Unidade excluída.'; await load(); }
onMounted(load);
</script>
