<template>
    <details class="my-4 rounded-xl border border-[var(--wn-line)] p-4">
        <summary class="cursor-pointer text-sm font-semibold">Vigência e recorrência das mensalidades</summary>
        <p class="mt-3 text-sm text-[var(--wn-muted)]">Revise competências faltantes e datas de pausa ou encerramento. Cobranças existentes serão preservadas e nenhuma mensagem será enviada.</p>
        <button v-if="!loaded" class="btn-secondary mt-3" :disabled="busy" @click="load">Carregar assinatura</button>
        <p v-if="error" role="alert" class="mt-3 text-sm text-rose-700">{{ error }}</p>
        <p v-if="success" role="status" class="mt-3 text-sm text-emerald-700">{{ success }}</p>
        <form v-if="loaded" class="mt-4 space-y-3" @submit.prevent="preview">
            <label class="block text-sm">Primeiro vencimento da recorrência<input v-model="form.start" required type="date" class="form-control mt-1" :disabled="busy" @input="invalidate" /></label>
            <label class="block text-sm">Pausar a partir de<input v-model="form.pause" type="date" class="form-control mt-1" :disabled="busy" @input="invalidate" /></label>
            <label class="block text-sm">Encerrar a partir de<input v-model="form.end" type="date" class="form-control mt-1" :disabled="busy" @input="invalidate" /></label>
            <p class="text-xs text-[var(--wn-muted)]">Essas datas impedem novas competências a partir do dia indicado. Confira se o valor atual do plano também corresponde aos meses antigos.</p>
            <button class="btn-secondary" :disabled="busy">Conferir competências</button>
            <div v-if="quote">
                <p class="text-sm font-semibold">{{ quote.items.length }} cobranças a gerar · {{ money(quote.total) }}</p>
                <ul class="my-3 space-y-1 text-sm"><li v-for="item in quote.items" :key="item.dueDate">{{ item.competence }} · {{ item.dueDate.split('-').reverse().join('/') }} · {{ money(item.value) }}</li></ul>
                <label class="flex items-start gap-2 text-sm"><input v-model="confirmed" type="checkbox" :disabled="busy" />Conferi os valores e a vigência e autorizo gerar as competências listadas.</label>
                <button type="button" class="btn-primary mt-3" :disabled="busy || !confirmed" @click="save">Confirmar recorrência</button>
            </div>
        </form>
    </details>
</template>

<script setup>
import { ref } from 'vue';
const props = defineProps({ studentId: { type: Number, required: true } });
const emit = defineEmits(['updated']);
const form = ref({ start: '', pause: '', end: '' });
const loaded = ref(false), busy = ref(false), error = ref(''), success = ref(''), quote = ref(null), confirmed = ref(false);
const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const invalidate = () => { quote.value = null; confirmed.value = false; success.value = ''; };
async function run(action) {
    busy.value = true; error.value = '';
    try { await action(); } catch (e) { error.value = e.response?.data?.message || 'Não foi possível consultar a recorrência.'; }
    finally { busy.value = false; }
}
async function load() {
    await run(async () => {
        const { data } = await window.axios.get(`/api/collections/${props.studentId}`);
        const subscription = data.subscription;
        if (!subscription?.auto_renovacao || subscription.status !== 'ativa') { error.value = 'Esta assinatura não tem recorrência ativa.'; return; }
        form.value = { start: subscription.recorrencia_inicio?.slice(0, 10) || '', pause: subscription.pausa_em?.slice(0, 10) || '', end: subscription.encerramento_em?.slice(0, 10) || '' };
        loaded.value = true;
        const result = await window.axios.post(`/api/collections/${props.studentId}/recurrence`, form.value);
        form.value.start = result.data.start; quote.value = result.data;
    });
}
async function preview() {
    invalidate();
    await run(async () => {
        const { data } = await window.axios.post(`/api/collections/${props.studentId}/recurrence`, form.value);
        quote.value = data; form.value.start = data.start;
    });
}
async function save() {
    await run(async () => {
        await window.axios.post(`/api/collections/${props.studentId}/recurrence`, { ...form.value, confirm: true, quote: quote.value.quote });
        invalidate(); success.value = 'Recorrência atualizada. Nenhuma mensagem enviada.'; emit('updated');
    });
}
</script>
