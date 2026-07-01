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
                        <Field label="Telefone *" v-model="form.telefone" required />
                        <Field label="E-mail" v-model="form.email" type="email" />
                        <Field label="Data de nascimento" v-model="form.data_nascimento" type="date" />
                    </div>
                </section>

                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><CalendarDays class="h-5 w-5" /></div>
                        <div><h2 class="text-xl font-semibold">Matricula</h2><p class="text-sm text-[var(--wn-muted)]">Plano, responsavel e datas principais.</p></div>
                    </div>
                    <div class="mt-6 space-y-4">
                        <SelectField label="Plano *" v-model="form.plano" :options="planNames" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field label="Data de inicio *" v-model="form.data_inicio" type="date" required />
                            <Field label="Vencimento *" v-model="form.data_vencimento" type="date" required />
                        </div>
                        <SelectField label="Professor responsavel" v-model="form.treinador" :options="options.teachers" placeholder="Definir depois" />
                        <SelectField label="Unidade / Studio" v-model="form.unidade" :options="options.units" placeholder="Selecione uma unidade" />
                        <SelectField label="Status" v-model="form.status" :options="['ativo', 'avaliacao', 'pausado']" />
                    </div>
                </section>

                <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600 lg:col-span-2">{{ error }}</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end lg:col-span-2">
                    <RouterLink to="/alunos" class="btn-secondary justify-center">Cancelar</RouterLink>
                    <button type="submit" class="btn-secondary justify-center" :disabled="loading" @click="intent = 'complete'">Salvar e completar ficha</button>
                    <button type="submit" class="btn-primary justify-center" :disabled="loading" @click="intent = 'view'">{{ loading ? 'Salvando...' : 'Salvar aluno' }}</button>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { CalendarDays, Sparkles, UserRound } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';

const Field = defineComponent({
    props: { label: String, modelValue: [String, Number], type: { type: String, default: 'text' }, required: Boolean },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [
        h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
        h('input', { value: props.modelValue, type: props.type, required: props.required, class: 'form-control', onInput: (event) => emit('update:modelValue', event.target.value) }),
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
const today = new Date();
const nextMonth = new Date();
nextMonth.setMonth(today.getMonth() + 1);
const toInputDate = (date) => date.toISOString().slice(0, 10);
const form = reactive({ nome: '', telefone: '', email: '', data_nascimento: '', plano: 'Plano Mensal', data_inicio: toInputDate(today), data_vencimento: toInputDate(nextMonth), treinador: '', unidade: '', status: 'ativo', objetivo: 'Saude', auto_renovacao: true, metodo_pagamento: 'PIX' });
const planNames = computed(() => options.plans.length ? options.plans.map((plan) => plan.nome) : ['Plano Mensal']);

onMounted(async () => {
    const { data } = await window.axios.get('/api/students/options');
    Object.assign(options, data);
});

const submit = async () => {
    loading.value = true;
    error.value = '';
    try {
        const selectedPlan = options.plans.find((plan) => plan.nome === form.plano);
        const { data } = await window.axios.post('/api/students', { ...form, valor_mensal: selectedPlan?.valor_mensal ?? 120 });
        router.push({ path: `/alunos/${data.student.id}`, query: intent.value === 'complete' ? { tab: 'profile', edit: '1' } : {} });
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar o aluno.';
    } finally { loading.value = false; }
};
</script>
