<template>
    <div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @click.self="close" @keydown.esc.stop="close">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="generate-charge-title" tabindex="-1" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @keydown.tab="trapFocus">
            <div class="flex items-center justify-between gap-4"><h2 id="generate-charge-title" class="text-xl font-bold">Gerar cobrança</h2><button class="icon-button" type="button" aria-label="Fechar" :disabled="busy" @click="close"><X :size="20" /></button></div>
            <p class="mt-2 text-sm text-[var(--wn-muted)]">Selecione o aluno e confira a próxima mensalidade do plano.</p>
            <ActionFeedback :message="error" type="error" />
            <form v-if="!student" class="mt-5" @submit.prevent="findStudents">
                <label for="charge-student-query" class="mb-2 block text-sm font-semibold">Nome do aluno</label>
                <div class="flex gap-2"><input id="charge-student-query" ref="searchInput" v-model="query" class="form-control min-w-0" placeholder="Digite pelo menos 2 letras" required minlength="2" maxlength="100" :disabled="searching" /><button class="btn-secondary" :disabled="searching || query.trim().length < 2">{{ searching ? 'Buscando…' : 'Buscar' }}</button></div>
                <p v-if="searched && !students.length && !searching" role="status" class="mt-4 text-sm text-[var(--wn-muted)]">Nenhum aluno encontrado. Tente outro nome.</p>
                <div class="mt-4 grid gap-2"><button v-for="item in students" :key="item.id" type="button" class="flex items-center justify-between gap-3 rounded-xl border border-[var(--wn-line)] p-3 text-left hover:bg-[var(--wn-surface-soft)]" :disabled="searching" @click="selectStudent(item)"><span><strong class="block text-sm">{{ item.name }}</strong><small class="text-[var(--wn-muted)]">{{ item.plan }}</small></span><ChevronRight :size="18" /></button></div>
            </form>
            <template v-else>
                <div class="mt-5 flex items-center justify-between gap-3 rounded-xl bg-[var(--wn-surface-soft)] p-4"><strong>{{ student.name }}</strong><button class="text-sm underline" :disabled="busy" @click="student = null; error = ''">Trocar aluno</button></div>
                <template v-if="student.financial?.nextDueDate && student.financial?.subscription?.status === 'ativa'">
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-[var(--wn-muted)]">Plano</dt><dd class="mt-1 font-semibold">{{ student.financial.subscription.plan }}</dd></div><div><dt class="text-[var(--wn-muted)]">Valor do plano</dt><dd class="mt-1 font-semibold">{{ money(student.financial.monthlyValue) }}</dd></div><div><dt class="text-[var(--wn-muted)]">Próximo vencimento</dt><dd class="mt-1 font-semibold">{{ student.financial.nextDueDate }}</dd></div></dl>
                    <p class="mt-5 text-xs leading-5 text-[var(--wn-muted)]">Se já houver uma cobrança nesta competência, ela será mantida com seu valor original. Nenhuma mensagem será enviada ao aluno.</p>
                    <div class="mt-6 flex flex-wrap justify-end gap-3"><button class="btn-secondary" :disabled="busy" @click="close">Cancelar</button><button class="btn-primary" :disabled="busy" @click="generate">{{ busy ? 'Gerando…' : 'Confirmar cobrança' }}</button></div>
                </template>
                <div v-else class="mt-5"><p class="text-sm text-[var(--wn-muted)]">Este aluno precisa de uma assinatura ativa com vencimento definido. Confira o plano no perfil antes de gerar a cobrança.</p><RouterLink :to="`/alunos/${student.id}?tab=financial`" class="btn-primary mt-5 justify-center" @click="close">Abrir financeiro do aluno</RouterLink></div>
            </template>
        </section>
    </div>
</template>
<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { X, ChevronRight } from 'lucide-vue-next';
const emit = defineEmits(['close', 'created']);
const dialog = ref(null), searchInput = ref(null), query = ref(''), students = ref([]), student = ref(null), error = ref('');
const searching = ref(false), searched = ref(false), busy = ref(false);
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
let previousFocus, alive = true;
function close() { if (!busy.value) emit('close'); }
async function findStudents() {
    if (query.value.trim().length < 2 || searching.value) return;
    searching.value = true; error.value = ''; students.value = [];
    try { const { data } = await window.axios.get('/api/students', { params: { q: query.value.trim(), tipo: 'aluno' } }); if (alive) { students.value = data.students; searched.value = true; } }
    catch { if (alive) error.value = 'Não foi possível buscar alunos. Tente novamente.'; }
    finally { searching.value = false; }
}
async function selectStudent(item) {
    searching.value = true; error.value = '';
    try { const { data } = await window.axios.get(`/api/students/${item.id}`); if (alive) { student.value = data.student; await nextTick(); dialog.value?.querySelector('button:not(:disabled)')?.focus(); } }
    catch { if (alive) error.value = 'Não foi possível carregar o aluno. Tente novamente.'; }
    finally { searching.value = false; }
}
async function generate() {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try { const { data } = await window.axios.post(`/api/students/${student.value.id}/charges`); emit('created', data.charge); }
    catch (e) { error.value = e.response?.data?.message || 'Não foi possível gerar a cobrança. Tente novamente.'; }
    finally { busy.value = false; }
}
function trapFocus(event) { const elements = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href]')]; const first = elements[0], last = elements.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
onMounted(() => { previousFocus = document.activeElement; searchInput.value?.focus(); });
onBeforeUnmount(() => { alive = false; previousFocus?.focus(); });
</script>
