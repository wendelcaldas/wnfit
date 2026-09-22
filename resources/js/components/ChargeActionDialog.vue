<template>
    <div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/40 p-4" @click.self="close" @keydown.esc="close">
        <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="charge-action-title" tabindex="-1" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @keydown.tab="trapFocus">
            <div class="flex items-start justify-between gap-4"><h2 id="charge-action-title" class="text-xl font-bold">{{ action === 'message' ? 'Mensagem de cobrança' : 'Registrar pagamento' }}</h2><button type="button" aria-label="Fechar" class="icon-button" :disabled="busy" @click="close"><X class="h-5 w-5" /></button></div>
            <p class="mt-3 text-sm text-[var(--wn-muted)]">{{ charge.competence }} · {{ money(charge.value) }} · vencimento {{ charge.dueDate }}</p>
            <p v-if="error" role="alert" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
            <p v-if="notice" role="status" class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ notice }}</p>
            <template v-if="action === 'message'">
                <p v-if="loading" class="mt-5 text-sm">Preparando a mensagem padrão…</p>
                <form v-else-if="needsPhone" class="mt-5" @submit.prevent="prepare(true)">
                    <p class="text-sm text-[var(--wn-muted)]">Informe o número usado pelo aluno no WhatsApp. Ele será salvo no cadastro para os próximos contatos.</p>
                    <label class="mt-4 block text-sm font-semibold">Telefone com DDD<input ref="phoneInput" v-model="phone" type="tel" autocomplete="tel" required maxlength="30" placeholder="(71) 99999-9999" class="form-control mt-2" :disabled="busy" /></label>
                    <button class="btn-primary mt-4" :disabled="busy || !phone.trim()">Salvar telefone e continuar</button>
                </form>
                <button v-else-if="!message" class="btn-secondary mt-4" @click="prepare">Tentar novamente</button>
                <template v-else>
                    <label class="mt-5 block text-sm font-semibold">Texto da mensagem<textarea v-model="content" class="form-control mt-2" style="min-height: 16rem" maxlength="5000" :disabled="busy || ready" /></label>
                    <p class="mt-2 text-xs text-[var(--wn-muted)]">{{ ready ? 'O texto foi salvo no histórico. Confirme o envio somente depois de enviar ao aluno.' : 'Revise e personalize o texto. Você fará o envio no WhatsApp Web.' }}</p>
                    <div class="mt-5 flex flex-wrap gap-2"><button class="btn-secondary" :disabled="busy || !content.trim()" @click="copy">Copiar texto</button><button class="btn-primary" :disabled="busy || !content.trim()" @click="openWhatsApp">Abrir WhatsApp Web</button></div>
                    <button v-if="ready" class="btn-secondary mt-4 w-full justify-center" :disabled="busy" @click="markSent">Já enviei: registrar no histórico</button>
                </template>
            </template>
            <form v-else class="mt-5" @submit.prevent="pay">
                <p class="text-sm text-[var(--wn-muted)]">Confirme a forma utilizada para pagar integralmente esta cobrança. O recebimento será registrado com a data de hoje.</p>
                <label class="mt-4 block text-sm font-semibold">Forma de pagamento<select ref="methodInput" v-model="method" required class="form-control mt-2" :disabled="busy"><option disabled value="">Selecione a forma de pagamento</option><option>PIX</option><option>Crédito</option><option>Débito</option><option>Dinheiro</option><option>Transferência</option><option>Boleto</option></select></label>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" :disabled="busy" @click="close">Cancelar</button><button class="btn-primary" :disabled="busy || !method">{{ busy ? 'Registrando…' : 'Confirmar pagamento' }}</button></div>
            </form>
        </section>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { X } from 'lucide-vue-next';
const props = defineProps({ charge: { type: Object, required: true }, action: { type: String, required: true } });
const emit = defineEmits(['close', 'updated']);
const dialog = ref(null), methodInput = ref(null), busy = ref(false), loading = ref(false);
const message = ref(null), content = ref(''), method = ref(''), ready = ref(false), error = ref(''), notice = ref('');
const needsPhone = ref(false), phone = ref(''), phoneInput = ref(null);
const money = value => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
let previousFocus;
const close = () => { if (!busy.value) emit('close'); };
async function run(action) {
    busy.value = true; error.value = ''; notice.value = '';
    try { await action(); } catch (e) {
        if (e.response?.data?.errors?.telefone) needsPhone.value = true;
        error.value = e.response?.data?.errors?.telefone?.[0] || e.response?.data?.message || e.message || 'Não foi possível concluir. Tente novamente.';
    }
    finally { busy.value = false; }
}
async function prepare(savePhone = false) {
    loading.value = true;
    await run(async () => {
        const { data } = await window.axios.post(`/api/charges/${props.charge.id}/send`, { manual: true, ...(savePhone === true ? { telefone: phone.value } : {}) });
        if (!data.charge.lastMessage?.manualUrl) throw new Error('Não foi possível preparar a mensagem manual.');
        message.value = data.charge.lastMessage; content.value = message.value.content;
        needsPhone.value = false;
        if (savePhone === true) { notice.value = 'Telefone salvo no cadastro. Revise a mensagem para continuar.'; emit('updated', 'Telefone do aluno atualizado.'); }
    });
    loading.value = false;
    await nextTick(); if (needsPhone.value) phoneInput.value?.focus(); else dialog.value?.querySelector('textarea')?.focus();
}
async function saveDraft() {
    const { data } = await window.axios.patch(`/api/messages/${message.value.id}`, { content: content.value });
    message.value = { ...message.value, ...data.message };
}
async function openWhatsApp() {
    const popup = window.open('about:blank', '_blank');
    if (!popup) { error.value = 'Permita a abertura de uma nova janela para acessar o WhatsApp Web.'; return; }
    popup.opener = null;
    await run(async () => {
        try {
            await saveDraft();
            const url = new URL(message.value.manualUrl);
            const phone = url.pathname.replace(/\D/g, '');
            if (!phone) throw new Error('Confira o telefone do aluno antes de enviar.');
            await window.axios.post(`/api/messages/${message.value.id}/opened`);
            popup.location.href = `https://web.whatsapp.com/send?${new URLSearchParams({ phone, text: content.value })}`;
            ready.value = true;
        } catch (e) { popup.close(); throw e; }
    });
}
async function copy() {
    await run(async () => { await navigator.clipboard.writeText(content.value); await saveDraft(); ready.value = true; notice.value = 'Texto copiado. Cole na conversa do aluno e envie.'; });
}
async function markSent() {
    await run(async () => { await window.axios.post(`/api/messages/${message.value.id}/sent`); emit('updated', 'Envio manual registrado no histórico.'); emit('close'); });
}
async function pay() {
    await run(async () => { await window.axios.post(`/api/charges/${props.charge.id}/pay`, { method: method.value }); emit('updated', `Pagamento registrado em ${method.value}.`); emit('close'); });
}
function trapFocus(event) {
    const nodes = [...dialog.value.querySelectorAll('button:not(:disabled),input:not(:disabled),textarea:not(:disabled),select:not(:disabled)')];
    const first = nodes[0], last = nodes.at(-1);
    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
onMounted(async () => { previousFocus = document.activeElement; dialog.value?.focus(); if (props.action === 'message') await prepare(); else methodInput.value?.focus(); });
onBeforeUnmount(() => previousFocus?.focus());
</script>
