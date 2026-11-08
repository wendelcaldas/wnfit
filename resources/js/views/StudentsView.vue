<template>
    <AppShell
        visual-refresh
        eyebrow="Alunos"
        title="Alunos"
        description="Acompanhe cada pessoa. Valorize cada evolução."
        search-placeholder="Buscar alunos, planos, status..."
    >
        <template #page-actions>
            <RouterLink to="/alunos/novo" class="btn-primary justify-center gap-2">
                <Plus class="h-5 w-5" />
                Novo aluno
            </RouterLink>
        </template>

        <section class="summary-metrics" aria-label="Indicadores dos alunos">
            <article v-for="item in summary" :key="item.label" class="stat-card summary-metric" :title="item.type === 'active' ? 'Com cadastro ativo' : item.caption">
                <component :is="item.icon" class="metric-icon" :class="item.iconClass" aria-hidden="true" />
                <div class="metric-copy">
                    <p class="metric-label">{{ item.label }}</p>
                    <p class="metric-value">{{ item.value }}</p>
                </div>
                <span class="sr-only">{{ item.type === 'active' ? 'Com cadastro ativo' : item.caption }}</span>
            </article>
        </section>

        <section class="panel-card mt-6 !p-0">
            <div class="admin-student-filters grid items-end gap-4 border-b border-[var(--wn-line)] p-5 xl:grid-cols-[1.6fr_repeat(4,minmax(0,0.9fr))_auto]">
                <label class="input-shell self-end">
                    <Search class="h-5 w-5 text-[var(--wn-muted)]" />
                    <input v-model="filters.q" type="text" class="auth-input" aria-label="Buscar aluno" placeholder="Buscar por nome, e-mail ou telefone..." @input="loadStudents" />
                </label>

                <label class="student-filter"><span class="text-xs font-semibold text-[var(--wn-muted)]">Modalidade</span><select v-model="filters.modalidade" class="form-control" @change="loadStudents"><option value="todos">Todas</option><option v-for="option in studentModalities" :key="option.value" :value="option.value">{{ option.label }}</option><option value="nao_informado">Não informado</option></select></label>

                <label class="student-filter">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Status do aluno</span>
                    <select v-model="filters.status" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option value="ativo">Ativo</option>
                        <option value="avaliacao">Em avaliação</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </label>

                <label class="student-filter">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Plano</span>
                    <select v-model="filters.plano" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option v-for="plan in options.plans" :key="plan" :value="plan">{{ plan }}</option>
                    </select>
                </label>

                <label class="student-filter">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Professor</span>
                    <select v-model="filters.professor" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option v-for="teacher in options.teachers" :key="teacher" :value="teacher">{{ teacher }}</option>
                    </select>
                </label>

                <button class="btn-secondary self-end gap-2" @click="resetFilters">
                    <SlidersHorizontal class="h-5 w-5" />
                    Limpar filtros
                </button>
            </div>

            <div class="admin-desktop-table overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--wn-line)]">
                    <thead class="bg-white">
                        <tr class="text-left text-sm font-semibold text-[var(--wn-muted)]">
                            <th class="px-5 py-4"><input type="checkbox" class="h-4 w-4 rounded border-[var(--wn-line)]" /></th>
                            <th class="px-5 py-4">Aluno</th>
                            <th class="px-5 py-4">Plano</th>
                            <th scope="col" class="px-5 py-4">Status</th>
                            <th scope="col" class="px-5 py-4">Financeiro</th>
                            <th class="px-5 py-4">Vencimento</th>
                            <th class="px-5 py-4">Último treino</th>
                            <th class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--wn-line)] bg-white">
                        <tr v-for="student in students" :key="student.id" class="transition hover:bg-[var(--wn-surface-soft)]">
                            <td class="px-5 py-4"><input type="checkbox" class="h-4 w-4 rounded border-[var(--wn-line)]" /></td>
                            <td class="px-5 py-4">
                                <RouterLink :to="`/alunos/${student.id}`" class="flex items-center gap-3">
                                    <div class="grid h-10 w-10 place-items-center rounded-full bg-[var(--wn-neutral-soft)] text-sm font-bold text-[var(--wn-neutral-strong)]">
                                        {{ student.initials }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-[var(--wn-ink)]">{{ student.name }}</p>
                                        <p class="text-xs text-[var(--wn-muted)]">{{ student.phone }} • {{ student.email }}</p>
                                    </div>
                                </RouterLink>
                            </td>
                            <td class="px-5 py-4 text-sm text-[var(--wn-ink)]">{{ student.plan }}<small class="block text-xs text-[var(--wn-muted)]">{{ student.modalityLabel }}</small></td>
                            <td class="px-5 py-4"><span class="student-status-text" :class="student.statusClass">{{ student.status }}</span></td>
                            <td class="px-5 py-4"><span class="student-status-text" :class="student.financialStatusClass">{{ student.financialStatus }}</span></td>
                            <td class="px-5 py-4 text-sm text-[var(--wn-muted)]">{{ student.dueDate }}</td>
                            <td class="px-5 py-4 text-sm text-[var(--wn-muted)]">{{ student.lastWorkout }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end">
                                    <RouterLink :to="`/alunos/${student.id}`" class="icon-button">
                                        <MoreVertical class="h-4 w-4" />
                                    </RouterLink>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <article v-for="student in students" :key="`mobile-${student.id}`" class="admin-mobile-card">
                <RouterLink :to="`/alunos/${student.id}`" class="font-semibold">{{ student.name }}</RouterLink>
                <dl class="admin-mobile-details">
                    <div><dt>Status</dt><dd><span class="student-status-text" :class="student.statusClass">{{ student.status }}</span></dd></div>
                    <div><dt>Financeiro</dt><dd><span class="student-status-text" :class="student.financialStatusClass">{{ student.financialStatus }}</span></dd></div>
                    <div><dt>Plano</dt><dd>{{ student.plan }}</dd></div>
                    <div><dt>Modalidade</dt><dd>{{ student.modalityLabel }}</dd></div>
                    <div><dt>Vencimento</dt><dd>{{ student.dueDate }}</dd></div>
                    <div><dt>Último treino</dt><dd>{{ student.lastWorkout }}</dd></div>
                </dl>
                <RouterLink :to="`/alunos/${student.id}`" class="btn-secondary justify-center">Ver aluno <ArrowRight :size="16" /></RouterLink>
            </article>

            <div class="flex items-center justify-between border-t border-[var(--wn-line)] px-5 py-4 text-sm text-[var(--wn-muted)]">
                <span>Mostrando 1 a {{ students.length }} de {{ summary[0]?.value ?? 0 }} alunos</span>
                <div class="flex gap-2">
                    <button class="icon-button">1</button>
                    <button class="icon-button">2</button>
                    <button class="icon-button">3</button>
                </div>
            </div>
        </section>
    </AppShell>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowRight, AlertTriangle, Hourglass, MoreVertical, Plus, Search, SlidersHorizontal, UserCheck, Users } from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';
import { studentModalities } from '../data/studentModalities';

const summaryMeta = {
    students: { icon: Users, iconWrapClass: 'bg-[var(--wn-primary-soft)]', iconClass: 'text-[var(--wn-primary-strong)]' },
    active: { icon: UserCheck, iconWrapClass: 'bg-[var(--wn-primary-soft)]', iconClass: 'text-[var(--wn-primary-strong)]' },
    expiring: { icon: Hourglass, iconWrapClass: 'bg-orange-50', iconClass: 'text-orange-500' },
    overdue: { icon: AlertTriangle, iconWrapClass: 'bg-red-50', iconClass: 'text-red-500' },
};

const summary = ref([]);
const students = ref([]);
const options = reactive({ plans: [], teachers: [] });
const filters = reactive({ tipo:'aluno', modalidade: 'todos', q: '', status: 'todos', plano: 'todos', professor: 'todos' });

const loadStudents = async () => {
    const { data } = await window.axios.get('/api/students', { params: filters });
    summary.value = data.summary.map((item) => ({ ...item, ...(summaryMeta[item.type] ?? summaryMeta.students) }));
    students.value = data.students;
    options.plans = data.filters.plans;
    options.teachers = data.filters.teachers;
};

const resetFilters = () => {
    filters.q = '';
    filters.modalidade = 'todos';
    filters.status = 'todos';
    filters.plano = 'todos';
    filters.professor = 'todos';
    loadStudents();
};

onMounted(loadStudents);
</script>

<style scoped>
.student-status-text { display: inline; padding: 0; border-radius: 0; background: transparent; }
.student-status-text.badge-warning { color: #b45309; }
.student-filter { display: flex; flex-direction: column; gap: .5rem; min-width: 0; }
.admin-student-filters > .input-shell,
.admin-student-filters .form-control,
.admin-student-filters > button { height: 3.125rem; min-height: 3.125rem; }
.admin-student-filters > button { white-space: nowrap; }
</style>
