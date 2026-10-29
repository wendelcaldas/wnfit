<template>
    <AppShell eyebrow="Configurações > Planos" title="Planos" description="Personalize os planos e valores oferecidos aos seus alunos.">
        <SettingsNavigation />
        <p v-if="notice" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ notice }}</p>
        <p v-if="loadError" role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{{ loadError }} <button class="underline" @click="load">Tentar novamente</button></p>
        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="panel-card !p-0 overflow-hidden" aria-labelledby="plans-title">
                <div class="flex items-center justify-between gap-3 border-b border-[var(--wn-line)] p-5"><div><h2 id="plans-title" class="text-lg font-semibold">Planos do seu negócio</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Planos ativos ficam disponíveis no cadastro de alunos.</p></div><button v-if="canManage" class="btn-primary whitespace-nowrap" :disabled="saving" @click="create"><Plus :size="16" />Novo plano</button></div>
                <p v-if="loading" class="p-8 text-center text-sm text-[var(--wn-muted)]">Carregando planos…</p>
                <p v-else-if="!plans.length && !loadError" class="p-8 text-center text-sm text-[var(--wn-muted)]">Nenhum plano cadastrado. Crie seu primeiro plano ao lado.</p>
                <div v-else class="divide-y divide-[var(--wn-line)]"><article v-for="plan in plans" :key="plan.id" class="flex flex-wrap items-center gap-4 p-5" :class="{ 'bg-[var(--wn-primary-soft)]': editingId === plan.id }"><span class="grid h-10 w-10 place-items-center rounded-xl bg-[var(--wn-surface-soft)] text-[var(--wn-primary-strong)]"><Tags :size="19" /></span><div class="min-w-0 flex-1"><h3 class="break-words font-semibold">{{ plan.name }}</h3><p class="mt-1 text-xs text-[var(--wn-muted)]">{{ cycles[plan.cycle] }} · {{ plan.subscriptions }} {{ plan.subscriptions === 1 ? 'assinatura' : 'assinaturas' }}</p></div><div class="text-right"><strong class="block text-lg tabular-nums">{{ money(plan.value) }}</strong><span class="text-xs text-[var(--wn-muted)]">por {{ cycleUnits[plan.cycle] }}</span></div><span :class="plan.active ? 'badge-success' : 'badge-muted'">{{ plan.active ? 'Ativo' : 'Inativo' }}</span><button v-if="canManage" class="btn-secondary" :disabled="saving" :aria-label="'Editar plano ' + plan.name" @click="edit(plan)"><Pencil :size="15" />Editar</button></article></div>
            </section>
            <aside v-if="canManage" ref="editor" class="panel-card" aria-labelledby="plan-editor-title">
                <h2 id="plan-editor-title" class="text-lg font-semibold">{{ editingId ? 'Editar plano' : 'Novo plano' }}</h2>
                <form class="mt-5 grid gap-4" @submit.prevent="save">
                    <label class="grid gap-2 text-sm">Nome do plano<input ref="nameInput" v-model="form.name" class="form-control" required maxlength="80" placeholder="Ex.: Funcional 3x por semana" :disabled="saving" /></label>
                    <label class="grid gap-2 text-sm">Ciclo de cobrança<select v-model="form.cycle" class="form-control" :disabled="saving || linked"><option v-for="(label, key) in cycles" :key="key" :value="key">{{ label }}</option></select></label>
                    <p v-if="linked" class="text-xs leading-5 text-[var(--wn-muted)]">Este plano já tem assinaturas. Para mudar o ciclo, crie um novo plano.</p>
                    <label class="grid gap-2 text-sm">Valor por {{ cycleUnits[form.cycle] }} (R$)<input v-model="form.value" type="number" class="form-control" required min="0.01" max="99999999.99" step="0.01" :disabled="saving" /></label>
                    <p class="text-xs leading-5 text-[var(--wn-muted)]">O valor é cobrado uma vez por ciclo. Um plano anual gera uma cobrança a cada 12 meses.</p>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.active" type="checkbox" :disabled="saving" />Disponível para novas matrículas</label>
                    <p v-if="editingId" class="rounded-xl bg-[var(--wn-surface-soft)] p-3 text-xs leading-5 text-[var(--wn-muted)]">Alterar o valor afeta as próximas cobranças geradas, inclusive das assinaturas atuais. Cobranças já geradas mantêm seus valores. Desativar o plano impede novas matrículas; as assinaturas atuais continuam.</p>
                    <p v-if="error" role="alert" class="text-sm text-rose-700">{{ error }}</p>
                    <div class="flex gap-2"><button class="btn-primary flex-1 justify-center" :disabled="saving || loading">{{ saving ? 'Salvando…' : editingId ? 'Salvar alterações' : 'Criar plano' }}</button><button v-if="editingId" type="button" class="btn-secondary" :disabled="saving" @click="create">Cancelar</button></div>
                </form>
            </aside>
            <aside v-else-if="!loading && !loadError" class="panel-card text-sm text-[var(--wn-muted)]">A criação e edição de planos estão disponíveis ao proprietário e aos administradores.</aside>
        </div>
    </AppShell>
</template>
<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { Plus, Tags, Pencil } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import SettingsNavigation from '../components/SettingsNavigation.vue';
const plans = ref([]), loading = ref(true), saving = ref(false), canManage = ref(false), editingId = ref(null), error = ref(''), loadError = ref(''), notice = ref('');
const editor = ref(null), nameInput = ref(null);
const form = reactive({ name: '', value: '', cycle: 'mensal', active: true });
const cycles = { mensal: 'Mensal', trimestral: 'Trimestral', semestral: 'Semestral', anual: 'Anual' };
const cycleUnits = { mensal: 'mês', trimestral: 'trimestre', semestral: 'semestre', anual: 'ano' };
const linked = computed(() => (plans.value.find(plan => plan.id === editingId.value)?.subscriptions ?? 0) > 0);
const money = value => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
async function load() {
    loading.value = true; loadError.value = '';
    try { const { data } = await window.axios.get('/api/organization/plans'); plans.value = data.plans; canManage.value = data.canManage; }
    catch { loadError.value = 'Não foi possível carregar os planos.'; }
    finally { loading.value = false; }
}
async function focusEditor() { await nextTick(); nameInput.value?.focus({ preventScroll: true }); editor.value?.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); }
function create() { editingId.value = null; Object.assign(form, { name: '', value: '', cycle: 'mensal', active: true }); error.value = ''; focusEditor(); }
function edit(plan) { editingId.value = plan.id; Object.assign(form, { name: plan.name, value: plan.value, cycle: plan.cycle, active: plan.active }); error.value = ''; focusEditor(); }
async function save() {
    if (saving.value) return;
    saving.value = true; error.value = ''; notice.value = '';
    try {
        if (editingId.value) await window.axios.put('/api/organization/plans/' + editingId.value, { ...form });
        else await window.axios.post('/api/organization/plans', { ...form });
        notice.value = editingId.value ? 'Plano atualizado.' : 'Plano criado e disponível no cadastro de alunos.';
        editingId.value = null; Object.assign(form, { name: '', value: '', cycle: 'mensal', active: true }); await load();
    } catch (e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] || e.response?.data?.message || 'Não foi possível salvar o plano.'; }
    finally { saving.value = false; }
}
onMounted(load);
</script>
