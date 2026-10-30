<template>
    <AppShell
        eyebrow="Alunos > Novo aluno"
        title="Cadastro rapido"
        description="Cadastre o essencial agora. O restante pode ser complementado na ficha do aluno."
        search-placeholder="Buscar alunos, treinos, planos..."
    >
        <div class="mx-auto max-w-5xl">
            <div class="mb-5 flex items-center gap-3 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-primary-soft)] px-5 py-4 text-sm text-[var(--wn-neutral-strong)]">
                <Sparkles class="h-5 w-5 shrink-0 text-[var(--wn-primary-strong)]" />
                <p><strong>Cadastro sem atrito:</strong> depois de salvar, voce podera completar dados pessoais, saude e anamnese na ficha do aluno.</p>
            </div>

            <form class="grid gap-5 lg:grid-cols-2" @submit.prevent="submit">
                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><UserRound class="h-5 w-5" /></div>
                        <div><h2 class="text-xl font-semibold">Dados essenciais</h2><p class="text-sm text-[var(--wn-muted)]">Como podemos identificar e contatar o aluno.</p></div>
                    </div>
                    <div class="mt-6 space-y-4">
                        <Field label="Nome completo *" v-model="form.nome" required />
                        <Field label="Telefone *" v-model="form.telefone" type="tel" mask="phone" required />
                        <Field label="E-mail" v-model="form.email" type="email" />
                        <Field label="Data de nascimento" v-model="form.data_nascimento" type="date" />
                        <div class="rounded-xl bg-[var(--wn-primary-soft)] p-4"><label class="block text-sm font-medium">Usuário do portal<input v-model="form.username" class="form-control mt-2" placeholder="Sugerido pelo nome" minlength="3" maxlength="60" pattern="[a-z0-9][a-z0-9._\-]+" @input="usernameEdited = true" /></label><p class="mt-2 text-xs leading-5 text-[var(--wn-muted)]">A senha provisória será gerada ao salvar. O aluno vai escolher uma nova senha no primeiro acesso.</p></div>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><CalendarDays class="h-5 w-5" /></div>
                        <div><h2 class="text-xl font-semibold">Matricula</h2><p class="text-sm text-[var(--wn-muted)]">Plano, responsavel e datas principais.</p></div>
                    </div>
                    <div class="mt-6 space-y-4">
                        <label class="block space-y-2"><span class="text-sm font-medium text-[var(--wn-muted)]">Plano *</span><select v-model="form.plan_id" class="form-control" required><option disabled value="">Selecione um plano</option><option v-for="plan in options.plans" :key="plan.id" :value="plan.id">{{ plan.nome }} · {{ money(plan.valor_mensal) }} / {{ cycleUnits[plan.ciclo] }}</option></select></label>
                        <RouterLink to="/configuracoes/planos" class="inline-block text-xs font-semibold text-[var(--wn-primary-strong)]">Gerenciar planos</RouterLink>
                        <p v-if="plansLoaded && !options.plans.length" class="text-xs text-[var(--wn-muted)]">Nenhum plano ativo disponível. Cadastre ou ative um plano em Configurações.</p>
                        <label class="block space-y-2"><span class="text-sm font-medium text-[var(--wn-muted)]">Modalidade de acompanhamento *</span><select v-model="form.modalidade" class="form-control" required><option disabled value="">Selecione a modalidade</option><option v-for="option in studentModalities" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field label="Data de inicio *" v-model="form.data_inicio" type="date" required />
                            <Field label="Vencimento *" v-model="form.data_vencimento" type="date" required />
                        </div>
                        <SelectField label="Professor responsavel" v-model="form.treinador" :options="options.teachers" placeholder="Definir depois" />
                        <label class="block space-y-2"><span class="text-sm font-medium text-[var(--wn-muted)]">Unidade / Studio</span><select v-model="form.unit_id" class="form-control" required><option v-for="unit in options.units" :key="unit.id" :value="unit.id">{{ unit.nome }}{{ unit.padrao ? ' · Padrão' : '' }}</option></select></label>
                        <RouterLink to="/configuracoes/unidades" class="inline-block text-xs font-semibold text-[var(--wn-primary-strong)]">Gerenciar unidades</RouterLink>
                        <SelectField label="Status" v-model="form.status" :options="['ativo', 'avaliacao', 'pausado']" />
                    </div>
                </section>

                <ActionFeedback :message="error" type="error" />
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end lg:col-span-2">
                    <RouterLink to="/alunos" class="btn-secondary justify-center">Cancelar</RouterLink>
                    <button type="submit" class="btn-secondary justify-center" :disabled="loading || !form.plan_id" @click="intent = 'complete'">Salvar e completar ficha</button>
                    <button type="submit" class="btn-primary justify-center" :disabled="loading || !form.plan_id" @click="intent = 'view'">{{ loading ? 'Salvando...' : 'Salvar aluno' }}</button>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch, withDirectives } from 'vue';
import { inputMask } from '../forms/inputMasks';
import { RouterLink, useRouter } from 'vue-router';
import { CalendarDays, Sparkles, UserRound } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import { studentModalities } from '../data/studentModalities';
import { useStudentCredentialsStore } from '../stores/studentCredentials';
const usernameEdited = ref(false);

const Field = defineComponent({
    props: { label: String, modelValue: [String, Number], type: { type: String, default: 'text' }, mask: String, maxlength: { type: [String, Number], default: 255 }, required: Boolean },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [
        h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
        withDirectives(h('input', { value: props.modelValue, type: props.type, required: props.required, maxlength: props.maxlength, max: props.type === 'date' && props.label.includes('nascimento') ? new Date().toLocaleDateString('sv-SE') : undefined, class: 'form-control', onInput: (event) => emit('update:modelValue', event.target.value) }), props.mask ? [[inputMask, props.mask]] : []),
    ]),
});

const SelectField = defineComponent({
    props: { label: String, modelValue: String, options: { type: Array, default: () => [] }, placeholder: String },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [
        h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
        h('select', { value: props.modelValue, class: 'form-control', onChange: (event) => emit('update:modelValue', event.target.value) }, [
            props.placeholder ? h('option', { value: '' }, props.placeholder) : null,
            ...props.options.map((option) => h('option', { value: typeof option === 'string' ? option : option.nome }, typeof option === 'string' ? option : option.nome)),
        ]),
    ]),
});

const router = useRouter();
const loading = ref(false);
const error = ref('');
const intent = ref('view');
const options = reactive({ plans: [], teachers: [], units: [] });
const plansLoaded = ref(false);
const today = new Date();
const nextMonth = new Date();
nextMonth.setMonth(today.getMonth() + 1);
const toInputDate = (date) => date.toISOString().slice(0, 10);
const form = reactive({ modalidade: '', nome: '', telefone: '', email: '', data_nascimento: '', plan_id: '', data_inicio: toInputDate(today), data_vencimento: toInputDate(nextMonth), treinador: '', unit_id: '', status: 'ativo', objetivo: 'Saude', auto_renovacao: true, metodo_pagamento: 'PIX' });
const cycleUnits = { mensal: 'mês', trimestral: 'trimestre', semestral: 'semestre', anual: 'ano' };
const money = value => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
let usernameTimer; let suggestionRequest = 0;
watch(() => form.nome, () => { clearTimeout(usernameTimer); const request = ++suggestionRequest; if (usernameEdited.value || !form.nome.trim()) return; usernameTimer = setTimeout(async () => { try { const { data } = await window.axios.get('/api/student-access/suggest', { params: { name: form.nome } }); if (request === suggestionRequest && !usernameEdited.value) form.username = data.username; } catch { /* Server also generates a unique username on save. */ } }, 300); });

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/students/options');
        Object.assign(options, data);
        form.plan_id = options.plans.find(plan => plan.nome === 'Plano Mensal')?.id ?? options.plans[0]?.id ?? '';
        form.unit_id = data.defaultUnitId;
        plansLoaded.value = true;
    } catch { error.value = 'Não foi possível carregar os planos. Atualize a página para tentar novamente.'; }
});

const submit = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post('/api/students', { ...form });
        useStudentCredentialsStore().set(data.student.id, data.credentials);
        router.push({ path: `/alunos/${data.student.id}`, query: intent.value === 'complete' ? { tab: 'profile', edit: '1' } : { tab: 'access' } });
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar o aluno.';
    } finally { loading.value = false; }
};
</script>
