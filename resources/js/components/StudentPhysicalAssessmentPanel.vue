<template>
    <section class="panel-card assessment-panel" aria-label="Avaliação física">
        <header class="assessment-header">
            <div><h2 class="text-xl font-semibold">Avaliação física</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Medidas e histórico de acompanhamento.</p></div>
            <div v-if="!editing" class="assessment-toolbar"><button v-if="current" class="btn-secondary" type="button" :disabled="comparing" :title="comparing ? 'Selecione apenas uma avaliação para editar' : undefined" @click="beginEdit"><Pencil class="h-4 w-4" />Editar avaliação</button><button class="btn-primary" type="button" :disabled="loading" @click="beginNew"><Plus class="h-4 w-4" />Nova avaliação</button></div>
        </header>
        <p v-if="loading" class="mt-5 text-sm" role="status">Carregando avaliações…</p>
        <ActionFeedback :message="error" type="error" :action="loadFailed ? load : undefined" />
        <ActionFeedback v-model="message" />
        <template v-if="!loading && !loadFailed">
            <div v-if="!assessments.length && !editing" class="assessment-empty"><p class="font-semibold">Nenhuma avaliação registrada.</p><p class="mt-2 text-sm text-[var(--wn-muted)]">Registre medidas aos poucos e acompanhe a evolução nas próximas avaliações.</p><p v-if="student.health.weight || student.health.height" class="mt-3 text-xs text-[var(--wn-muted)]">Cadastro anterior, sem data de avaliação: {{ student.health.weight ? `${format(student.health.weight)} kg` : '' }} {{ student.health.height ? `· ${format(student.health.height)} cm` : '' }}. Esses valores não foram transformados em avaliação.</p></div>
            <section v-if="assessments.length" class="assessment-history mt-5" aria-label="Selecionar avaliações">
                <h3 class="font-semibold">Histórico de avaliações</h3>
                <div class="assessment-history-options">
                    <button v-for="item in assessments" :key="item.id" type="button" :disabled="editing || (selectedIds.length === 2 && !selectedIds.includes(item.id))" :aria-pressed="selectedIds.includes(item.id)" @click="select(item)">
                        <Check v-if="selectedIds.includes(item.id)" class="h-4 w-4" /><span>{{ assessmentName(item) }}<small>{{ date(item.assessed_on) }}</small></span>
                    </button>
                </div>
            </section>
            <form v-if="current || editing" class="mt-5" @submit.prevent="save">
                <div v-if="editing" class="assessment-header mb-4">
                    <p class="text-sm font-semibold">{{ editing ? (editingId ? 'Editando avaliação' : 'Nova avaliação') : comparing ? 'Comparação entre avaliações' : `${assessmentName(current)} · ${date(current.assessed_on)}` }}</p>

                </div>
                <fieldset :disabled="saving">
                    <legend class="sr-only">Dados da avaliação</legend>
                    <template v-if="editing">
                        <label class="assessment-field mb-4"><span>Nome da avaliação (opcional)</span><input class="form-control" v-model="form.name" maxlength="120" :placeholder="editingId ? assessmentName(current) : 'Avaliação ' + (assessments.length + 1)" /><small v-if="errors.name" role="alert">{{ errors.name[0] }}</small></label>
                        <label class="assessment-field assessment-date"><span>Data da avaliação *</span><input class="form-control" type="date" v-model="form.assessed_on" :max="today" required /><small v-if="errors.assessed_on" role="alert">{{ errors.assessed_on[0] }}</small></label>
                        <p class="mt-4 text-sm text-[var(--wn-muted)]">Preencha apenas as medidas que coletou. É necessário informar pelo menos uma.</p>
                        <h3 class="mt-5 font-semibold">Dados gerais</h3>
                        <div class="assessment-input-grid mt-3">
                            <div v-for="field in generalFields" :key="field.key" class="assessment-field">
                                <label :for="'assessment-' + field.key">{{ field.label }} ({{ field.unit }})</label>
                                <input :id="'assessment-' + field.key" class="form-control" type="text" inputmode="decimal" v-model="form[field.key]" :aria-invalid="!!errors[field.key]" />
                                <small v-if="errors[field.key]" role="alert">{{ errors[field.key][0] }}</small>
                            </div>
                        </div>
                        <h3 class="mt-6 font-semibold">Circunferências <span class="text-sm font-normal text-[var(--wn-muted)]">(opcional, em cm)</span></h3>
                        <p class="mt-1 text-xs text-[var(--wn-muted)]">Direita e esquerda correspondem aos lados do aluno.</p>
                        <div class="assessment-input-grid mt-3">
                            <div v-for="field in circumferenceFields" :key="field.key" class="assessment-field">
                                <label :for="'assessment-' + field.key">{{ field.label }}</label>
                                <input :id="'assessment-' + field.key" class="form-control" type="text" inputmode="decimal" v-model="form[field.key]" :aria-invalid="!!errors[field.key]" />
                                <small v-if="errors[field.key]" role="alert">{{ errors[field.key][0] }}</small>
                            </div>
                        </div>
                    </template>
                    <div v-else class="assessment-overview">
                        <div class="assessment-table" role="table" aria-label="Medidas da avaliação">
                            <div class="assessment-table-head" role="row">
                                <span role="columnheader">Medida</span>
                                <span role="columnheader">{{ previous ? assessmentName(previous) : 'Anterior' }}<small>{{ previous ? date(previous.assessed_on) : '—' }}</small></span>
                                <span role="columnheader" class="assessment-recent">{{ assessmentName(current) }}<small>{{ date(current.assessed_on) }} · {{ comparing ? 'Mais recente' : 'Selecionada' }}{{ isCurrentMonth(current) ? ' · Mês atual' : '' }}</small></span>
                                <span role="columnheader">Variação</span>
                            </div>
                            <div v-for="field in recordedFields" :key="field.key" class="assessment-overview-row" role="row">
                                <div role="cell" class="assessment-measure-name">{{ field.label }}</div>
                                <div role="cell"><span class="assessment-mobile-label">Anterior</span>{{ valueLabel(previous?.measures[field.key], field.unit) }}</div>
                                <div role="cell" class="assessment-recent"><span class="assessment-mobile-label">{{ comparing ? 'Mais recente' : 'Selecionada' }} · {{ date(current.assessed_on) }}</span><strong class="assessment-current">{{ valueLabel(current.measures[field.key], field.unit) }}</strong></div>
                                    <div role="cell"><span class="assessment-mobile-label">Variação</span><span class="assessment-delta" :class="{ 'assessment-delta--up': delta(field.key) > 0, 'assessment-delta--down': delta(field.key) < 0 }"><ArrowUp v-if="delta(field.key) > 0" class="h-4 w-4" aria-hidden="true" /><ArrowDown v-else-if="delta(field.key) < 0" class="h-4 w-4" aria-hidden="true" />{{ changeLabel(field) }}</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 assessment-extra">
                        <p v-if="comparing" class="text-xs text-[var(--wn-muted)]">Informações da avaliação mais recente selecionada: {{ date(current.assessed_on) }} (#{{ current.id }}).</p>
                        <label class="assessment-field"><span>Método / origem da medição</span><input v-if="editing" class="form-control" v-model="form.method" maxlength="255" placeholder="Ex.: fita métrica, balança, bioimpedância" /><span v-else>{{ current.method || 'Não informado' }}</span><small v-if="errors.method" role="alert">{{ errors.method[0] }}</small></label>
                        <label class="assessment-field"><span>Observações da avaliação</span><textarea v-if="editing" class="form-control" rows="3" v-model="form.notes" maxlength="5000"></textarea><span v-else class="whitespace-pre-wrap">{{ current.notes || 'Sem observações.' }}</span><small v-if="errors.notes" role="alert">{{ errors.notes[0] }}</small></label>
                    </div>
                    <p v-if="!editing" class="mt-4 text-xs text-[var(--wn-muted)]">Registrada por {{ current.created_by || 'Usuário indisponível' }} · Última edição por {{ current.updated_by || 'Usuário indisponível' }}</p>
                    <div v-if="editing" class="assessment-actions mt-5"><button class="btn-secondary" type="button" @click="cancel">Cancelar</button><button class="btn-primary" type="submit">{{ saving ? 'Salvando…' : 'Salvar avaliação' }}</button></div>
                </fieldset>
            </form>

        </template>
    </section>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { Pencil, Plus, Check, ArrowUp, ArrowDown } from 'lucide-vue-next';
const props = defineProps({ student: { type: Object, required: true } });
const emit = defineEmits(['saved']);
const assessments = ref([]), selectedIds = ref([]), loading = ref(true), loadFailed = ref(false), error = ref(''), message = ref(''), errors = ref({}), editing = ref(false), editingId = ref(null), saving = ref(false),  form = ref({});
const generalFields = [{ key: 'weight', label: 'Peso', unit: 'kg' }, { key: 'height', label: 'Altura', unit: 'cm' }, { key: 'body_fat', label: 'Gordura corporal', unit: '%' }];
const regions = { arm: { label: 'Braço', fields: [{ key: 'arm_right', label: 'Direito do aluno' }, { key: 'arm_left', label: 'Esquerdo do aluno' }] }, chest: { label: 'Tórax', fields: [{ key: 'chest', label: 'Circunferência' }] }, waist: { label: 'Cintura', fields: [{ key: 'waist', label: 'Circunferência' }] }, hip: { label: 'Quadril', fields: [{ key: 'hip', label: 'Circunferência' }] }, thigh: { label: 'Coxa', fields: [{ key: 'thigh_right', label: 'Direita do aluno' }, { key: 'thigh_left', label: 'Esquerda do aluno' }] }, calf: { label: 'Panturrilha', fields: [{ key: 'calf_right', label: 'Direita do aluno' }, { key: 'calf_left', label: 'Esquerda do aluno' }] } };
const circumferenceFields = Object.values(regions).flatMap(r => r.fields.map(f => ({ key: f.key, label: r.fields.length > 1 ? r.label + ' · ' + f.label.toLowerCase().replace(' do aluno', '') : r.label, unit: 'cm' })));
const overviewFields = [...generalFields, ...circumferenceFields];
const selectedAssessments = computed(() => assessments.value.filter(item => selectedIds.value.includes(item.id)).sort((a, b) => b.assessed_on.localeCompare(a.assessed_on) || b.id - a.id));
const assessmentName = item => item.name?.trim() || 'Avaliação ' + (assessments.value.filter(a => a.id < item.id).length + 1);
const isCurrentMonth = item => item.assessed_on.slice(0, 7) === today.slice(0, 7);
const current = computed(() => selectedAssessments.value[0] ?? null);
const previous = computed(() => selectedAssessments.value[1] ?? null);
const comparing = computed(() => selectedAssessments.value.length === 2);
const recordedFields = computed(() => overviewFields.filter(field => current.value?.measures[field.key] != null || (comparing.value && previous.value?.measures[field.key] != null)));
const changeLabel = field => {
    const difference = delta(field.key);
    if (difference === null) return '—';
    if (difference === 0) return 'Sem alteração';
    return (difference > 0 ? '+' : '−') + format(Math.abs(difference)) + ' ' + (field.key === 'body_fat' ? 'p.p.' : field.unit);
};
const measureKeys = [...generalFields.map(f => f.key), ...Object.values(regions).flatMap(r => r.fields.map(f => f.key))];
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bahia', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const format = value => Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
const valueLabel = (value, unit) => value === null || value === undefined ? '—' : `${format(value)} ${unit}`;
const date = value => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR');
const delta = key => current.value?.measures[key] != null && previous.value?.measures[key] != null ? Math.round((current.value.measures[key] - previous.value.measures[key]) * 100) / 100 : null;
const clearMessages = () => { error.value = ''; message.value = ''; errors.value = {}; };
const select = item => {
    if (editing.value) return;
    if (selectedIds.value.includes(item.id)) {
        selectedIds.value = selectedIds.value.filter(id => id !== item.id);
    } else if (selectedIds.value.length < 2) selectedIds.value = [...selectedIds.value, item.id];
    clearMessages();
};
function beginNew() { clearMessages(); editingId.value = null; form.value = Object.fromEntries(measureKeys.map(k => [k, ''])); Object.assign(form.value, { name: '', assessed_on: today, method: '', notes: '' }); editing.value = true; }
function beginEdit() { clearMessages(); editingId.value = current.value.id; form.value = { ...Object.fromEntries(measureKeys.map(k => [k, current.value.measures[k] == null ? '' : String(current.value.measures[k]).replace('.', ',')])), name: current.value.name ?? '', assessed_on: current.value.assessed_on, method: current.value.method ?? '', notes: current.value.notes ?? '' }; editing.value = true; }
function cancel() { editing.value = false; clearMessages(); }
async function load() {
    loading.value = true; loadFailed.value = false; error.value = '';
    try { assessments.value = (await window.axios.get(`/api/students/${props.student.id}/physical-assessments`)).data.assessments; selectedIds.value = assessments.value.length ? [assessments.value[0].id] : []; }
    catch { loadFailed.value = true; error.value = 'Não foi possível carregar as avaliações.'; }
    finally { loading.value = false; }
}
async function save() {
    if (saving.value) return;
    clearMessages();
    const payload = { name: form.value.name?.trim() || null, assessed_on: form.value.assessed_on, method: form.value.method || null, notes: form.value.notes || null };
    for (const key of measureKeys) {
        const raw = String(form.value[key] ?? '').trim();
        if (raw && !/^\d+(?:[.,]\d{1,2})?$/.test(raw)) { errors.value[key] = ['Use um número positivo com até duas casas decimais.']; }
        payload[key] = raw ? raw.replace(',', '.') : null;
    }
    if (Object.keys(errors.value).length) { error.value = 'Revise as medidas informadas, antes de salvar.'; return; }
    saving.value = true;
    try {
        const url = `/api/students/${props.student.id}/physical-assessments`;
        const { data } = editingId.value ? await window.axios.put(`${url}/${editingId.value}`, payload) : await window.axios.post(url, payload);
        editing.value = false; await load();
        if (assessments.value.some(a => a.id === data.assessment.id)) selectedIds.value = [data.assessment.id];
        if (!loadFailed.value) message.value = 'Avaliação salva.';
        emit('saved');
    } catch (e) { errors.value = e.response?.data?.errors ?? {}; error.value = Object.values(errors.value).flat()[0] || 'Não foi possível salvar. Tente novamente.'; }
    finally { saving.value = false; }
}
onMounted(load);
</script>
