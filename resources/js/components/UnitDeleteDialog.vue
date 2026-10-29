<template>
    <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @click.self="close" @keydown.esc.stop="close">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="delete-unit-title" aria-describedby="delete-unit-description" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @keydown.tab="trapFocus">
            <h2 id="delete-unit-title" class="text-lg font-bold">Excluir unidade</h2>
            <p id="delete-unit-description" class="mt-3 text-sm leading-6">Excluir <strong>{{ unit.name }}</strong>? Esta ação remove a unidade definitivamente. Os cadastros dos alunos serão preservados.</p>
            <form class="mt-4 grid gap-4" @submit.prevent="remove">
                <template v-if="unit.students"><p class="text-sm text-[var(--wn-muted)]">Esta unidade tem {{ unit.students }} {{ unit.students === 1 ? 'aluno vinculado' : 'alunos vinculados' }}. Escolha para onde transferir antes de excluir.</p><label class="grid gap-2 text-sm">Transferir alunos para<select v-model="destinationId" class="form-control" required :disabled="busy"><option v-for="item in destinations" :key="item.id" :value="item.id">{{ item.name }}{{ item.default ? ' · Padrão' : '' }}</option></select></label></template>
                <ActionFeedback :message="error" type="error" />
                <div class="mt-2 flex justify-end gap-3"><button ref="cancelButton" type="button" class="btn-secondary" :disabled="busy" @click="close">Cancelar</button><button class="btn-primary !bg-rose-700 !text-white" :disabled="busy || (unit.students > 0 && !destinationId)">{{ busy ? 'Excluindo…' : unit.students ? 'Transferir e excluir' : 'Excluir unidade' }}</button></div>
            </form>
        </section>
    </div>
</template>
<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps({ unit: { type: Object, required: true }, units: { type: Array, required: true } });
const emit = defineEmits(['close', 'deleted']);
const destinations = computed(() => props.units.filter(item => item.id !== props.unit.id && item.active));
const destinationId = ref(destinations.value.find(item => item.default)?.id ?? destinations.value[0]?.id ?? '');
const busy = ref(false), error = ref(''), dialog = ref(null), cancelButton = ref(null);
let previousFocus;
function close() { if (!busy.value) emit('close'); }
function trapFocus(event) { const elements = [...dialog.value.querySelectorAll('button:not(:disabled), select:not(:disabled)')]; const first = elements[0], last = elements.at(-1); if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
async function remove() {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try { const { data } = await window.axios.delete('/api/organization/units/' + props.unit.id, { data: { destination_id: destinationId.value || null } }); emit('deleted', data.transferredStudents); }
    catch (e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] || e.response?.data?.message || 'Não foi possível excluir a unidade.'; }
    finally { busy.value = false; }
}
onMounted(() => { previousFocus = document.activeElement; cancelButton.value?.focus(); });
onBeforeUnmount(() => previousFocus?.focus());
</script>
