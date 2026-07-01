<template>
    <AppShell
        eyebrow="Alunos > Cadastrar aluno"
        title="Cadastro de aluno"
        description="Preencha os dados do aluno para criar ou editar o cadastro."
        search-placeholder="Buscar alunos, treinos, planos..."
    >
        <template #page-actions>
            <div class="flex justify-end gap-3">
                <RouterLink to="/alunos" class="btn-secondary justify-center">Cancelar</RouterLink>
                <button class="btn-primary justify-center" :disabled="loading" @click="submit">
                    {{ loading ? 'Salvando...' : 'Salvar aluno' }}
                </button>
            </div>
        </template>

        <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
            <section class="panel-card p-0">
                <div class="border-b border-[var(--wn-line)] p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                                <UserRound class="h-5 w-5" />
                            </div>
                            <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Dados pessoais</h2>
                        </div>
                        <button class="btn-secondary gap-2">
                            <Plus class="h-5 w-5" />
                            Adicionar foto
                        </button>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <Field label="Nome completo *" icon="user" v-model="form.nome" />
                        <Field label="Data de nascimento" type="date" v-model="form.data_nascimento" />
                        <SelectField label="Genero" v-model="form.genero" :options="['Feminino', 'Masculino', 'Outro']" />
                        <Field label="E-mail" icon="mail" type="email" v-model="form.email" />
                        <Field label="Telefone principal" icon="phone" v-model="form.telefone" />
                        <Field label="CPF" v-model="form.cpf" />
                        <Field label="RG" v-model="form.rg" />
                        <Field label="Profissao" v-model="form.profissao" />
                        <Field label="Endereco" class="md:col-span-2" icon="pin" v-model="form.endereco" />
                        <Field label="Numero" v-model="form.numero" />
                        <Field label="Complemento" v-model="form.complemento" />
                        <Field label="Bairro" v-model="form.bairro" />
                        <Field label="Cidade" v-model="form.cidade" />
                        <SelectField label="Estado" v-model="form.estado" :options="['SP', 'BA', 'RJ', 'MG', 'PR']" />
                        <Field label="CEP" v-model="form.cep" />
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <ClipboardList class="h-5 w-5" />
                        </div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Informacoes adicionais</h2>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-4">
                        <Field label="Peso (kg)" v-model="form.peso" />
                        <Field label="Altura (cm)" v-model="form.altura" />
                        <SelectField label="Objetivo principal *" v-model="form.objetivo" :options="['Emagrecimento', 'Ganho de massa', 'Saude', 'Performance', 'Definicao']" />
                        <SelectField label="Como conheceu?" v-model="form.como_conheceu" :options="['Indicacao', 'Instagram', 'Google', 'Passou na frente']" />
                        <label class="md:col-span-4 space-y-2">
                            <span class="text-sm font-medium text-[var(--wn-muted)]">Observacoes gerais</span>
                            <textarea v-model="form.observacoes" rows="4" class="form-control" placeholder="Observacoes, restricoes, preferencias e historico relevante."></textarea>
                        </label>
                    </div>
                </div>

                <p v-if="error" class="mx-6 mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600">
                    {{ error }}
                </p>
            </section>

            <aside class="space-y-5">
                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <CalendarDays class="h-5 w-5" />
                        </div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Plano e status</h2>
                    </div>

                    <div class="mt-6 space-y-4">
                        <SelectField label="Plano *" v-model="form.plano" :options="planNames" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field label="Data de inicio *" type="date" v-model="form.data_inicio" />
                            <Field label="Data de vencimento *" type="date" v-model="form.data_vencimento" />
                        </div>
                        <SelectField label="Status *" v-model="form.status" :options="['ativo', 'avaliacao', 'pausado']" />
                        <label class="flex items-center gap-3 text-sm text-[var(--wn-muted)]">
                            <input v-model="form.auto_renovacao" type="checkbox" class="h-5 w-5 rounded border-[var(--wn-line)]" />
                            Renovacao automatica
                        </label>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <Users class="h-5 w-5" />
                        </div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Responsavel / Professor</h2>
                    </div>
                    <div class="mt-6 space-y-4">
                        <SelectField label="Professor responsavel" v-model="form.treinador" :options="options.teachers" />
                        <SelectField label="Unidade / Studio" v-model="form.unidade" :options="options.units" />
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Observacoes internas</h2>
                    <textarea v-model="form.observacoes" rows="4" class="form-control mt-4" placeholder="Preferencias e notas internas."></textarea>
                </section>
            </aside>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { CalendarDays, ClipboardList, Mail, MapPin, Phone, Plus, UserRound, Users } from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';

const icons = { user: UserRound, mail: Mail, phone: Phone, pin: MapPin };

const Field = defineComponent({
    props: { label: String, modelValue: [String, Number], type: { type: String, default: 'text' }, icon: String },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('label', { class: 'space-y-2 block' }, [
            h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
            h('div', { class: 'input-shell' }, [
                props.icon && icons[props.icon] ? h(icons[props.icon], { class: 'h-5 w-5 text-[var(--wn-muted)]' }) : null,
                h('input', {
                    value: props.modelValue,
                    type: props.type,
                    class: 'auth-input',
                    onInput: (event) => emit('update:modelValue', event.target.value),
                }),
            ]),
        ]);
    },
});

const SelectField = defineComponent({
    props: { label: String, modelValue: String, options: { type: Array, default: () => [] } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('label', { class: 'space-y-2 block' }, [
            h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
            h('select', {
                value: props.modelValue,
                class: 'form-control',
                onChange: (event) => emit('update:modelValue', event.target.value),
            }, props.options.map((option) => h('option', { value: typeof option === 'string' ? option : option.nome }, typeof option === 'string' ? option : option.nome))),
        ]);
    },
});

const router = useRouter();
const loading = ref(false);
const error = ref('');
const options = reactive({ plans: [], teachers: [], units: [] });
const today = new Date();
const nextMonth = new Date();
nextMonth.setMonth(today.getMonth() + 1);

const toInputDate = (date) => date.toISOString().slice(0, 10);

const form = reactive({
    nome: '',
    email: '',
    telefone: '',
    data_nascimento: '',
    genero: 'Feminino',
    cpf: '',
    rg: '',
    profissao: '',
    endereco: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    estado: 'SP',
    cep: '',
    peso: '',
    altura: '',
    objetivo: 'Emagrecimento',
    como_conheceu: 'Indicacao',
    observacoes: '',
    plano: 'Plano Mensal',
    status: 'ativo',
    data_inicio: toInputDate(today),
    data_vencimento: toInputDate(nextMonth),
    auto_renovacao: true,
    metodo_pagamento: 'PIX',
    treinador: 'Lucas Oliveira',
    unidade: 'Unidade Vila Madalena',
});

const planNames = computed(() => options.plans.length ? options.plans.map((plan) => plan.nome) : ['Plano Mensal']);

onMounted(async () => {
    const { data } = await window.axios.get('/api/students/options');
    options.plans = data.plans;
    options.teachers = data.teachers;
    options.units = data.units;
});

const submit = async () => {
    loading.value = true;
    error.value = '';

    try {
        const selectedPlan = options.plans.find((plan) => plan.nome === form.plano);
        const { data } = await window.axios.post('/api/students', {
            ...form,
            valor_mensal: selectedPlan?.valor_mensal ?? 120,
        });
        router.push(`/alunos/${data.student.id}`);
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar o aluno.';
    } finally {
        loading.value = false;
    }
};
</script>
