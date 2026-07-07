<template>
    <AppShell
        eyebrow="Alunos > Detalhe"
        :title="student?.name ?? 'Aluno'"
        description="Cadastro, evolução, comunicação e controle financeiro do aluno."
        search-placeholder="Buscar alunos, treinos, planos..."
    >
        <section v-if="student" class="panel-card p-0">
            <div class="p-6">
                <RouterLink to="/alunos" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--wn-muted)]">
                    <ArrowLeft class="h-4 w-4" />
                    Voltar para alunos
                </RouterLink>

                <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-5">
                        <div class="grid h-24 w-24 place-items-center rounded-full bg-[var(--wn-neutral-soft)] text-2xl font-bold text-[var(--wn-neutral-strong)]">
                            {{ student.initials }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-3xl font-bold text-[var(--wn-ink)]">{{ student.name }}</h1>
                                <span class="badge-success">{{ student.status }}</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-5 text-sm text-[var(--wn-muted)]">
                                <span class="inline-flex items-center gap-2"><Mail class="h-4 w-4" />{{ student.email }}</span>
                                <span class="inline-flex items-center gap-2"><Phone class="h-4 w-4" />{{ student.phone }}</span>
                                <span class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ student.city }}, {{ student.state }}</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.plan }}</span>
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.teacher }}</span>
                                <span class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-xs font-semibold text-[var(--wn-ink)]">{{ student.unit }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button class="btn-secondary gap-2" @click="openProfile"><Pencil class="h-5 w-5" />Editar aluno</button>
                        <button class="icon-button"><MoreHorizontal class="h-5 w-5" /></button>
                    </div>
                </div>
            </div>

            <div class="border-t border-[var(--wn-line)] px-6 py-4">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="font-medium">Perfil {{ student.profileCompletion }}% completo</span>
                    <button class="font-semibold text-[var(--wn-primary-strong)]" @click="openProfile">Completar perfil</button>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-[var(--wn-neutral-soft)]"><div class="h-full rounded-full bg-[var(--wn-primary-strong)]" :style="{ width: `${student.profileCompletion}%` }"></div></div>
            </div>

            <nav class="flex gap-2 overflow-x-auto border-t border-[var(--wn-line)] px-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="border-b-2 px-4 py-4 text-sm font-medium transition"
                    :class="activeTab === tab.key ? 'border-[var(--wn-primary)] text-[var(--wn-primary-strong)]' : 'border-transparent text-[var(--wn-muted)] hover:text-[var(--wn-ink)]'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </section>

        <section v-if="student && activeTab === 'overview'" class="mt-5 grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="space-y-5">
                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Resumo do aluno</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-4">
                        <MiniKpi label="Check-ins este mês" value="16" caption="+14% vs mês anterior" />
                        <MiniKpi label="Último treino" value="Hoje, 08:15" caption="Funcional - A" />
                        <MiniKpi label="Frequência média" value="84%" caption="Ótima frequência" />
                        <MiniKpi label="Dias ativo" value="28" caption="Nos últimos 30 dias" />
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Observações do professor</h2>
                    <p class="mt-4 rounded-xl border border-[var(--wn-line)] bg-white p-4 text-sm leading-6 text-[var(--wn-muted)]">
                        {{ student.observations || 'Nenhuma observação cadastrada.' }}
                    </p>
                </section>
            </div>

            <aside class="space-y-5">
                <PlanCard :student="student" />
                <section class="panel-card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Financeiro</h2>
                        <button class="text-sm font-semibold text-[var(--wn-primary-strong)]" @click="activeTab = 'financial'">Ver detalhes</button>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-[var(--wn-muted)]">Total pago</p>
                            <p class="mt-1 font-bold text-[var(--wn-ink)]">{{ money(student.financial.totalPaid) }}</p>
                        </div>
                        <div>
                            <p class="text-[var(--wn-muted)]">Em aberto</p>
                            <p class="mt-1 font-bold text-[var(--wn-primary-strong)]">{{ money(student.financial.openAmount) }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section v-if="student && activeTab === 'profile'" class="mt-5 grid gap-5 xl:grid-cols-[1fr_320px]">
            <form class="panel-card" @submit.prevent="saveProfile">
                <div class="flex items-center justify-between gap-4">
                    <div><h2 class="text-xl font-semibold">Dados pessoais</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Contato, documentos e endereco do aluno.</p></div>
                    <button class="btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar alteracoes' }}</button>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <ProfileField label="Nome completo *" v-model="profileForm.nome" required />
                    <ProfileField label="Telefone *" v-model="profileForm.telefone" required />
                    <ProfileField label="E-mail" v-model="profileForm.email" type="email" />
                    <ProfileField label="Data de nascimento" v-model="profileForm.data_nascimento" type="date" />
                    <ProfileField label="CPF" v-model="profileForm.cpf" /><ProfileField label="RG" v-model="profileForm.rg" />
                    <ProfileField label="Profissao" v-model="profileForm.profissao" /><ProfileField label="Genero" v-model="profileForm.genero" />
                    <ProfileField label="Endereco" v-model="profileForm.endereco" /><ProfileField label="Numero" v-model="profileForm.numero" />
                    <ProfileField label="Complemento" v-model="profileForm.complemento" /><ProfileField label="Bairro" v-model="profileForm.bairro" />
                    <ProfileField label="Cidade" v-model="profileForm.cidade" /><ProfileField label="Estado" v-model="profileForm.estado" maxlength="2" />
                    <ProfileField label="CEP" v-model="profileForm.cep" />
                </div>
                <FormMessage :text="formMessage" />
            </form>
            <aside class="space-y-5">
                <section class="panel-card"><h2 class="text-lg font-semibold">Contato de emergencia</h2><div class="mt-4 space-y-4"><ProfileField label="Nome do contato" v-model="profileForm.contato_emergencia" /><ProfileField label="Telefone" v-model="profileForm.telefone_emergencia" /></div></section>
                <section class="panel-card bg-[var(--wn-primary-soft)]"><h2 class="font-semibold">Preencha aos poucos</h2><p class="mt-2 text-sm leading-6 text-[var(--wn-muted)]">Os campos complementares ajudam no atendimento, mas nao bloqueiam a matricula.</p></section>
            </aside>
        </section>

        <section v-if="student && activeTab === 'health'" class="mt-5">
            <form class="panel-card" @submit.prevent="saveHealth">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="text-xl font-semibold">Saude e anamnese</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Informacoes para um acompanhamento mais seguro e individualizado.</p></div>
                    <button class="btn-primary justify-center" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar saude' }}</button>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <ProfileField label="Objetivo principal" v-model="healthForm.objetivo" /><ProfileField label="Peso (kg)" v-model="healthForm.peso" type="number" /><ProfileField label="Altura (cm)" v-model="healthForm.altura" type="number" />
                    <TextField label="Restricoes medicas" v-model="healthForm.restricoes_medicas" /><TextField label="Lesoes ou limitacoes" v-model="healthForm.lesoes" /><TextField label="Medicamentos" v-model="healthForm.medicamentos" />
                    <div class="md:col-span-3"><TextField label="Observacoes do acompanhamento" v-model="healthForm.observacoes" /></div>
                </div>
                <FormMessage :text="formMessage" />
            </form>
        </section>

        <section v-if="student && activeTab === 'workouts'" class="mt-5 space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-xl font-semibold">Treino do aluno</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">Acompanhe o programa atual ou atribua uma nova rotina.</p></div>
                <div class="flex flex-wrap gap-3"><button class="btn-secondary" type="button" @click="workoutPickerOpen = true"><Library class="h-5 w-5" />Usar modelo existente</button><RouterLink :to="`/treinos/novo?student=${student.id}`" class="btn-primary"><Plus class="h-5 w-5" />Criar novo treino</RouterLink></div>
            </div>

            <div v-if="workoutsLoading" class="panel-card p-10 text-center text-sm text-[var(--wn-muted)]">Carregando treino...</div>
            <section v-else-if="currentWorkout" class="panel-card p-0 overflow-hidden">
                <div class="flex flex-col gap-5 border-b border-[var(--wn-line)] bg-[var(--wn-primary-soft)] p-6 md:flex-row md:items-start md:justify-between"><div><div class="flex items-center gap-3"><span class="badge-success">Treino atual</span><span class="text-sm font-medium capitalize text-[var(--wn-muted)]">{{ currentWorkout.level }}</span></div><h3 class="mt-3 text-2xl font-bold">{{ currentWorkout.name }}</h3><p class="mt-2 text-sm text-[var(--wn-muted)]">{{ currentWorkout.objective }}<span v-if="currentWorkout.description"> · {{ currentWorkout.description }}</span></p></div><RouterLink :to="`/treinos/${currentWorkout.id}/editar`" class="btn-secondary shrink-0"><Pencil class="h-4 w-4" />Editar treino</RouterLink></div>
                <div class="grid gap-4 border-b border-[var(--wn-line)] p-5 sm:grid-cols-3"><MiniKpi label="Frequencia" :value="`${currentWorkout.sessionsPerWeek}x por semana`" caption="Sessoes planejadas" /><MiniKpi label="Duracao" :value="`${currentWorkout.durationWeeks} semanas`" caption="Ciclo do programa" /><MiniKpi label="Divisao" :value="`${currentWorkout.days.length} dias`" caption="Rotinas cadastradas" /></div>
                <div class="p-5"><h3 class="font-semibold">Dias do treino</h3><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><article v-for="(day, index) in currentWorkout.days" :key="day.id" class="rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4"><div class="flex items-center justify-between"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white text-sm font-bold">{{ index + 1 }}</span><span class="text-xs text-[var(--wn-muted)]">{{ day.exercisesCount }} exercicios</span></div><p class="mt-3 font-semibold">{{ day.name }}</p><p class="mt-1 text-sm text-[var(--wn-muted)]">{{ day.focus || 'Foco geral' }}</p></article></div></div>
            </section>
            <section v-else class="panel-card grid place-items-center py-14 text-center"><div><div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]"><Dumbbell class="h-7 w-7" /></div><h3 class="mt-4 text-lg font-semibold">Nenhum treino associado</h3><p class="mx-auto mt-2 max-w-md text-sm text-[var(--wn-muted)]">Crie um treino personalizado para {{ student.name }} ou aproveite um modelo da biblioteca.</p><div class="mt-5 flex flex-wrap justify-center gap-3"><button class="btn-secondary" @click="workoutPickerOpen = true">Usar modelo</button><RouterLink :to="`/treinos/novo?student=${student.id}`" class="btn-primary">Criar treino</RouterLink></div></div></section>
        </section>

        <div v-if="workoutPickerOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @click.self="workoutPickerOpen = false"><section class="flex max-h-[82vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-[var(--wn-line)] p-5"><div><h2 class="text-xl font-semibold">Escolher modelo de treino</h2><p class="mt-1 text-sm text-[var(--wn-muted)]">O modelo selecionado passara a ser o treino atual do aluno.</p></div><button class="icon-button" @click="workoutPickerOpen = false"><X class="h-4 w-4" /></button></header><div class="overflow-y-auto p-5"><div v-if="workoutTemplates.length" class="grid gap-3 sm:grid-cols-2"><button v-for="workout in workoutTemplates" :key="workout.id" class="rounded-xl border p-4 text-left transition hover:border-[var(--wn-primary-strong)] hover:bg-[var(--wn-primary-soft)]" :class="workout.selected ? 'border-[var(--wn-primary-strong)] bg-[var(--wn-primary-soft)]' : 'border-[var(--wn-line)]'" :disabled="assigningWorkout" @click="assignWorkout(workout)"><div class="flex items-center justify-between gap-3"><span class="font-semibold">{{ workout.name }}</span><span v-if="workout.selected" class="badge-success">Atual</span></div><p class="mt-2 text-sm text-[var(--wn-muted)]">{{ workout.objective }} · {{ workout.sessionsPerWeek }}x/semana</p><p class="mt-3 text-xs text-[var(--wn-muted)]">{{ workout.daysCount }} dias · {{ workout.durationWeeks }} semanas</p></button></div><p v-else class="p-8 text-center text-sm text-[var(--wn-muted)]">Nenhum modelo ativo disponivel.</p></div></section></div>

        <section v-if="student && activeTab === 'financial'" class="mt-5 grid gap-5 xl:grid-cols-[1fr_420px]">
            <div class="space-y-5">
                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Resumo financeiro</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-5">
                        <MiniKpi label="Status financeiro" :value="student.financial.status" caption="Nenhuma pendência" />
                        <MiniKpi label="Valor mensal" :value="money(student.financial.monthlyValue)" :caption="student.plan" />
                        <MiniKpi label="Próximo vencimento" :value="student.financial.nextDueDate" caption="Cobrança recorrente" />
                        <MiniKpi label="Total pago" :value="money(student.financial.totalPaid)" caption="Histórico" />
                        <MiniKpi label="Em aberto" :value="money(student.financial.openAmount)" caption="Pendências" />
                    </div>
                </section>

                <section class="panel-card p-0">
                    <div class="flex flex-col gap-4 border-b border-[var(--wn-line)] p-5 md:flex-row md:items-center md:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Cobranças</h2>
                            <span class="badge-success">Todas</span>
                            <span class="badge-muted">Pagas</span>
                            <span class="badge-muted">Pendentes</span>
                            <span class="badge-muted">Atrasadas</span>
                        </div>
                        <button class="btn-secondary gap-2" @click="generateCharge"><Plus class="h-5 w-5" />Gerar cobrança</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[var(--wn-line)]">
                            <thead>
                                <tr class="text-left text-sm font-semibold text-[var(--wn-muted)]">
                                    <th class="px-5 py-4">Competência</th>
                                    <th class="px-5 py-4">Vencimento</th>
                                    <th class="px-5 py-4">Valor</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4">Forma de pagamento</th>
                                    <th class="px-5 py-4">Pagamento</th>
                                    <th class="px-5 py-4 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--wn-line)]">
                                <tr v-for="charge in student.financial.charges" :key="charge.id">
                                    <td class="px-5 py-4 text-sm">{{ charge.competence }}</td>
                                    <td class="px-5 py-4 text-sm">{{ charge.dueDate }}</td>
                                    <td class="px-5 py-4 text-sm">{{ money(charge.value) }}</td>
                                    <td class="px-5 py-4"><span :class="charge.statusClass">{{ labelStatus(charge.status) }}</span></td>
                                    <td class="px-5 py-4 text-sm">{{ charge.paymentMethod }}</td>
                                    <td class="px-5 py-4 text-sm text-[var(--wn-muted)]">{{ charge.paidAt }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button class="icon-button" title="Enviar cobrança" @click="sendCharge(charge.id)"><MessageCircle class="h-4 w-4" /></button>
                                            <button class="icon-button" title="Registrar pagamento" @click="payCharge(charge.id)"><ReceiptText class="h-4 w-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Histórico financeiro</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-5">
                        <article v-for="event in student.financial.timeline" :key="event.date + event.type" class="rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4">
                            <div class="mb-3 grid h-9 w-9 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                                <CheckCircle2 class="h-4 w-4" />
                            </div>
                            <p class="text-sm font-semibold text-[var(--wn-ink)]">{{ event.title }}</p>
                            <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ event.date }}</p>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <PlanCard :student="student" />
                <section class="panel-card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Automação de cobrança</h2>
                        <button class="btn-secondary">Editar regras</button>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-[var(--wn-muted)]">
                        <AutomationRow text="Enviar lembrete 2 dias antes do vencimento" />
                        <AutomationRow text="Enviar no dia do vencimento" />
                        <AutomationRow text="Enviar cobrança após 2 dias de atraso" />
                    </div>
                    <div class="mt-5 border-t border-[var(--wn-line)] pt-5 text-sm">
                        <p class="font-semibold text-[var(--wn-ink)]">Canal principal</p>
                        <p class="mt-2 text-[var(--wn-muted)]">WhatsApp</p>
                        <p class="mt-4 font-semibold text-[var(--wn-ink)]">Mensagem padrão</p>
                        <p class="mt-2 text-[var(--wn-muted)]">Olá, sua mensalidade venceu. Segue link para pagamento.</p>
                    </div>
                </section>
            </aside>
        </section>
    </AppShell>
</template>

<script setup>
import { defineComponent, h, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { ArrowLeft, CheckCircle2, Dumbbell, Library, Mail, MapPin, MessageCircle, MoreHorizontal, Pencil, Phone, Plus, ReceiptText, X } from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';

const MiniKpi = defineComponent({
    props: { label: String, value: [String, Number], caption: String },
    setup(props) {
        return () => h('article', { class: 'rounded-xl border border-[var(--wn-line)] bg-white p-4' }, [
            h('p', { class: 'text-xs text-[var(--wn-muted)]' }, props.label),
            h('p', { class: 'mt-2 text-xl font-bold text-[var(--wn-ink)]' }, props.value),
            h('p', { class: 'mt-2 text-xs text-[var(--wn-muted)]' }, props.caption),
        ]);
    },
});

const ProfileField = defineComponent({
    props: { label: String, modelValue: [String, Number], type: { type: String, default: 'text' }, required: Boolean, maxlength: [String, Number] },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [
        h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
        h('input', { value: props.modelValue ?? '', type: props.type, required: props.required, maxlength: props.maxlength, class: 'form-control', onInput: (event) => emit('update:modelValue', event.target.value) }),
    ]),
});

const TextField = defineComponent({
    props: { label: String, modelValue: String },
    emits: ['update:modelValue'],
    setup: (props, { emit }) => () => h('label', { class: 'block space-y-2' }, [
        h('span', { class: 'text-sm font-medium text-[var(--wn-muted)]' }, props.label),
        h('textarea', { value: props.modelValue ?? '', rows: 5, class: 'form-control', onInput: (event) => emit('update:modelValue', event.target.value) }),
    ]),
});

const FormMessage = defineComponent({
    props: { text: String },
    setup: (props) => () => props.text ? h('p', { class: 'mt-5 rounded-lg bg-[var(--wn-primary-soft)] px-4 py-3 text-sm text-[var(--wn-primary-strong)]' }, props.text) : null,
});

const PlanCard = defineComponent({
    props: { student: Object },
    setup(props) {
        return () => h('section', { class: 'panel-card' }, [
            h('div', { class: 'flex items-center justify-between' }, [
                h('h2', { class: 'text-xl font-semibold text-[var(--wn-ink)]' }, 'Plano e assinatura'),
                h('span', { class: 'badge-success' }, props.student.financial.subscription.status ?? 'Ativa'),
            ]),
            h('div', { class: 'mt-5 space-y-4 text-sm' }, [
                h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Plano atual'), h('p', { class: 'mt-1 font-semibold text-[var(--wn-ink)]' }, props.student.financial.subscription.plan)]),
                h('div', { class: 'grid grid-cols-2 gap-4 border-t border-[var(--wn-line)] pt-4' }, [
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Início do plano'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.subscription.startDate)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Próximo vencimento'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.nextDueDate)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Ciclo'), h('p', { class: 'mt-1 text-[var(--wn-ink)]' }, props.student.financial.subscription.cycle)]),
                    h('div', null, [h('p', { class: 'text-[var(--wn-muted)]' }, 'Renovação'), h('p', { class: 'mt-1 text-[var(--wn-primary-strong)] font-semibold' }, props.student.financial.subscription.autoRenew ? 'Ativada' : 'Desativada')]),
                ]),
                h('button', { class: 'btn-secondary w-full justify-center' }, 'Alterar plano'),
            ]),
        ]);
    },
});

const AutomationRow = defineComponent({
    props: { text: String },
    setup(props) {
        return () => h('div', { class: 'flex items-center justify-between gap-3' }, [
            h('span', { class: 'inline-flex items-center gap-2' }, [
                h(CheckCircle2, { class: 'h-4 w-4 text-[var(--wn-primary-strong)]' }),
                props.text,
            ]),
            h(MessageCircle, { class: 'h-4 w-4 text-[var(--wn-primary-strong)]' }),
        ]);
    },
});

const route = useRoute();
const student = ref(null);
const activeTab = ref(route.query.tab ?? 'overview');
const saving = ref(false);
const formMessage = ref('');
const profileForm = reactive({});
const healthForm = reactive({});
const currentWorkout = ref(null);
const workoutTemplates = ref([]);
const workoutsLoading = ref(false);
const workoutPickerOpen = ref(false);
const assigningWorkout = ref(false);
const tabs = [
    { key: 'overview', label: 'Visão geral' },
    { key: 'profile', label: 'Dados pessoais' },
    { key: 'health', label: 'Saude e anamnese' },
    { key: 'workouts', label: 'Treinos' },
    { key: 'evaluations', label: 'Avaliações' },
    { key: 'frequency', label: 'Frequência' },
    { key: 'financial', label: 'Financeiro' },
    { key: 'communications', label: 'Comunicações' },
    { key: 'files', label: 'Arquivos' },
    { key: 'history', label: 'Histórico' },
];

const money = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const labelStatus = (status) => ({ pago: 'Pago', pendente: 'Pendente', atrasado: 'Atrasado', cancelado: 'Cancelado' }[status] ?? status);

const loadStudent = async () => {
    const { data } = await window.axios.get(`/api/students/${route.params.id}`);
    student.value = data.student;
    Object.assign(profileForm, {
        nome: data.student.profile.name, email: data.student.profile.email, telefone: data.student.profile.phone,
        data_nascimento: data.student.profile.birthDateInput, genero: data.student.profile.gender, cpf: data.student.profile.cpf,
        rg: data.student.profile.rg, profissao: data.student.profile.profession, endereco: data.student.profile.address,
        numero: data.student.profile.number, complemento: data.student.profile.complement, bairro: data.student.profile.neighborhood,
        cidade: data.student.profile.city, estado: data.student.profile.state, cep: data.student.profile.zip,
        contato_emergencia: data.student.profile.emergencyContact, telefone_emergencia: data.student.profile.emergencyPhone,
    });
    Object.assign(healthForm, {
        objetivo: data.student.health.goal, peso: data.student.health.weight, altura: data.student.health.height,
        restricoes_medicas: data.student.health.medicalRestrictions, lesoes: data.student.health.injuries,
        medicamentos: data.student.health.medications, observacoes: data.student.health.notes,
    });
};

const saveChanges = async (payload) => {
    saving.value = true;
    formMessage.value = '';
    try {
        const { data } = await window.axios.patch(`/api/students/${student.value.id}`, payload);
        student.value = data.student;
        formMessage.value = 'Alteracoes salvas com sucesso.';
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        formMessage.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar as alteracoes.';
    } finally { saving.value = false; }
};

const saveProfile = () => saveChanges(profileForm);
const saveHealth = () => saveChanges(healthForm);
const openProfile = () => { activeTab.value = 'profile'; formMessage.value = ''; };

const loadWorkouts = async () => {
    workoutsLoading.value = true;
    try {
        const { data } = await window.axios.get(`/api/students/${route.params.id}/workouts`);
        currentWorkout.value = data.current;
        workoutTemplates.value = data.templates;
    } finally { workoutsLoading.value = false; }
};

const assignWorkout = async (workout) => {
    if (workout.selected) { workoutPickerOpen.value = false; return; }
    assigningWorkout.value = true;
    try {
        await window.axios.post(`/api/students/${student.value.id}/workouts/${workout.id}`);
        await loadWorkouts();
        workoutPickerOpen.value = false;
    } finally { assigningWorkout.value = false; }
};

const generateCharge = async () => {
    await window.axios.post(`/api/students/${student.value.id}/charges`);
    await loadStudent();
};

const sendCharge = async (id) => {
    await window.axios.post(`/api/charges/${id}/send`);
    await loadStudent();
};

const payCharge = async (id) => {
    await window.axios.post(`/api/charges/${id}/pay`);
    await loadStudent();
};

onMounted(async () => { await Promise.all([loadStudent(), loadWorkouts()]); });
</script>
