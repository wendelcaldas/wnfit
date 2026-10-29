<template>
    <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" @click.self="close" @keydown.esc.stop="close">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="income-title" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @keydown.tab="trapFocus">
            <div class="flex items-center justify-between"><h2 id="income-title" class="text-lg font-bold">Adicionar entrada</h2><button type="button" class="icon-button" aria-label="Fechar" :disabled="busy" @click="close"><X :size="20" /></button></div>
            <p class="mt-2 text-sm text-[var(--wn-muted)]">Registre um valor avulso já recebido.</p>
            <p v-if="error" role="alert" class="mt-4 text-sm text-rose-700">{{ error }}</p>
            <form class="mt-5 grid gap-4" @submit.prevent="save">
                <label class="grid gap-1 text-sm">Descrição<input ref="firstInput" v-model="description" class="form-control" required maxlength="180" placeholder="Ex.: aula avulsa · Ana" :disabled="busy" /></label>
                <div class="grid grid-cols-2 gap-3"><label class="grid gap-1 text-sm">Valor (R$)<input v-model="value" type="number" min="0.01" max="99999999.99" step="0.01" class="form-control" required :disabled="busy" /></label><label class="grid gap-1 text-sm">Recebido em<input v-model="date" type="date" :max="today" class="form-control" required :disabled="busy" /></label></div>
                <label class="grid gap-1 text-sm">Forma de pagamento<select v-model="method" class="form-control" :disabled="busy"><option v-for="item in methods" :key="item">{{ item }}</option></select></label>
                <div class="mt-2 flex justify-end gap-3"><button type="button" class="btn-secondary" :disabled="busy" @click="close">Cancelar</button><button class="btn-primary" :disabled="busy">{{ busy ? 'Salvando…' : 'Registrar entrada' }}</button></div>
            </form>
        </section>
    </div>
</template>
<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { X } from 'lucide-vue-next';
const emit = defineEmits(['close', 'created']);
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Bahia', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const description = ref(''), value = ref(''), date = ref(today), method = ref('PIX'), busy = ref(false), error = ref('');
const methods = ['PIX', 'Dinheiro', 'Transferência', 'Cartão', 'Boleto'];
const dialog = ref(null), firstInput = ref(null), requestId = crypto.randomUUID();
let previousFocus;
function close() { if (!busy.value) emit('close'); }
function trapFocus(event) {
    const elements = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)')];
    const first = elements[0], last = elements.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try {
        const { data } = await window.axios.post('/api/finance/income', { description: description.value, value: value.value, date: date.value, method: method.value, requestId });
        emit('created', data.month);
    } catch (e) { error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] || 'Não foi possível registrar a entrada. Tente novamente.'; }
    finally { busy.value = false; }
}
onMounted(() => { previousFocus = document.activeElement; firstInput.value?.focus(); });
onBeforeUnmount(() => previousFocus?.focus());
</script>
