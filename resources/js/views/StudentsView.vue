<template>
    <AppShell
        eyebrow="Alunos"
        title="Gestao de alunos"
        description="Veja, filtre e gerencie todos os alunos do seu studio."
        search-placeholder="Buscar alunos, planos, status..."
    >
        <div class="mb-4 flex justify-end">
            <RouterLink to="/alunos/novo" class="btn-primary justify-center gap-2">
                <Plus class="h-5 w-5" />
                Novo aluno
            </RouterLink>
        </div>

        <section class="grid gap-4 xl:grid-cols-4">
            <article v-for="item in summary" :key="item.label" class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="grid h-14 w-14 place-items-center rounded-2xl" :class="item.iconWrapClass">
                        <component :is="item.icon" class="h-6 w-6" :class="item.iconClass" />
                    </div>
                    <div>
                        <p class="text-sm text-[var(--wn-muted)]">{{ item.label }}</p>
                        <p class="mt-1 text-3xl font-bold text-[var(--wn-ink)]">{{ item.value }}</p>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">{{ item.caption }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="panel-card mt-6 p-0">
            <div class="grid gap-4 border-b border-[var(--wn-line)] p-5 xl:grid-cols-[1.6fr_repeat(3,0.9fr)_auto]">
                <label class="input-shell">
                    <Search class="h-5 w-5 text-[var(--wn-muted)]" />
                    <input v-model="filters.q" type="text" class="auth-input" placeholder="Buscar por nome, e-mail ou telefone..." @input="loadStudents" />
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Status</span>
                    <select v-model="filters.status" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option value="ativo">Ativo</option>
                        <option value="avaliacao">Em avaliacao</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Plano</span>
                    <select v-model="filters.plano" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option v-for="plan in options.plans" :key="plan" :value="plan">{{ plan }}</option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold text-[var(--wn-muted)]">Professor</span>
                    <select v-model="filters.professor" class="form-control" @change="loadStudents">
                        <option value="todos">Todos</option>
                        <option v-for="teacher in options.teachers" :key="teacher" :value="teacher">{{ teacher }}</option>
                    </select>
                </label>

                <button class="btn-secondary self-end gap-2" @click="resetFilters">
                    <SlidersHorizontal class="h-5 w-5" />
                    Filtros
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--wn-line)]">
                    <thead class="bg-white">
                        <tr class="text-left text-sm font-semibold text-[var(--wn-muted)]">
                            <th class="px-5 py-4"><input type="checkbox" class="h-4 w-4 rounded border-[var(--wn-line)]" /></th>
                            <th class="px-5 py-4">Aluno</th>
                            <th class="px-5 py-4">Plano</th>
                            <th class="px-5 py-4">Professor</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Vencimento</th>
                            <th class="px-5 py-4">Ultimo treino</th>
                            <th class="px-5 py-4 text-right">Acoes</th>
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
                            <td class="px-5 py-4 text-sm text-[var(--wn-ink)]">{{ student.plan }}</td>
                            <td class="px-5 py-4 text-sm text-[var(--wn-ink)]">{{ student.teacher }}</td>
                            <td class="px-5 py-4"><span :class="student.statusClass">{{ student.status }}</span></td>
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
import { AlertTriangle, Hourglass, MoreVertical, Plus, Search, SlidersHorizontal, UserCheck, Users } from 'lucide-vue-next';

import AppShell from '../components/AppShell.vue';

const summaryMeta = {
    students: { icon: Users, iconWrapClass: 'bg-[var(--wn-primary-soft)]', iconClass: 'text-[var(--wn-primary-strong)]' },
    active: { icon: UserCheck, iconWrapClass: 'bg-[var(--wn-primary-soft)]', iconClass: 'text-[var(--wn-primary-strong)]' },
    expiring: { icon: Hourglass, iconWrapClass: 'bg-orange-50', iconClass: 'text-orange-500' },
    overdue: { icon: AlertTriangle, iconWrapClass: 'bg-red-50', iconClass: 'text-red-500' },
};

const summary = ref([]);
const students = ref([]);
const options = reactive({ plans: [], teachers: [] });
const filters = reactive({ q: '', status: 'todos', plano: 'todos', professor: 'todos' });

const loadStudents = async () => {
    const { data } = await window.axios.get('/api/students', { params: filters });
    summary.value = data.summary.map((item) => ({ ...item, ...(summaryMeta[item.type] ?? summaryMeta.students) }));
    students.value = data.students;
    options.plans = data.filters.plans;
    options.teachers = data.filters.teachers;
};

const resetFilters = () => {
    filters.q = '';
    filters.status = 'todos';
    filters.plano = 'todos';
    filters.professor = 'todos';
    loadStudents();
};

onMounted(loadStudents);
</script>
