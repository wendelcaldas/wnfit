<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({ eventId: { type: Number, required: true }, registration: { type: Object, required: true } });
const emit = defineEmits(['updated']);
const open = ref(false), loading = ref(false), saving = ref(false), error = ref(''), query = ref('');
const candidates = ref({ data: [] }), selected = ref(null), creating = ref(false), result = ref(null), copied = ref(false);
let searchVersion = 0;
const base = computed(() => `/api/events/${props.eventId}/registrations/${props.registration.id}`);
const message = computed(() => result.value ? [
    `Olá, ${props.registration.name.split(' ')[0]}! Seu acesso ao WNFit:`,
    `${location.origin}/aluno/entrar`,
    `Usuário: ${result.value.student.username}`,
    result.value.credentials ? `Senha provisória: ${result.value.credentials.temporaryPassword}\nNo primeiro acesso, escolha sua senha pessoal.` : 'Use sua senha atual. Se precisar de ajuda, procure a equipe.',
].join('\n') : '');
function fail(e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Não foi possível conectar. Tente novamente.'; }
async function search(page = 1) {
    const version = ++searchVersion;
    loading.value = true; error.value = ''; selected.value = null; creating.value = false; candidates.value = { data: [] };
    try {
        const { data } = await window.axios.get(`${base.value}/students`, { params: { q: query.value, page } });
        if (version === searchVersion) candidates.value = data;
    } catch (e) { if (version === searchVersion) fail(e); }
    finally { if (version === searchVersion) loading.value = false; }
}
async function show() {
    open.value = true;
    if (props.registration.aluno_id) await save('link', props.registration.aluno_id);
    else await search();
}
async function save(mode, studentId = selected.value) {
    saving.value = true; error.value = ''; copied.value = false;
    try {
        const { data } = await window.axios.post(`${base.value}/access`, { mode, ...(mode === 'link' ? { student_id: studentId } : {}) });
        result.value = data;
        emit('updated', props.eventId);
    } catch (e) { fail(e); }
    finally { saving.value = false; }
}
async function copy() {
    try { await navigator.clipboard.writeText(message.value); copied.value = true; }
    catch { error.value = 'Selecione e copie a mensagem abaixo.'; }
}
function close() { open.value = false; result.value = null; error.value = ''; copied.value = false; }
</script>

<template>
    <div class="participant-access">
        <p v-if="registration.aluno">Aluno vinculado: <strong>{{ registration.aluno.nome }}</strong></p>
        <p v-else>Acesso de aluno ainda não vinculado.</p>
        <button v-if="!open" type="button" class="btn-secondary" @click="show">{{ registration.aluno_id ? 'Preparar mensagem de acesso' : 'Preparar acesso do aluno' }}</button>
        <section v-else class="access-panel" :aria-label="`Acesso de ${registration.name}`" :aria-busy="loading || saving">
            <h4>Acesso de {{ registration.name }}</h4>
            <ActionFeedback :message="error" type="error" />
            <template v-if="result">
                <p role="status">{{ result.message }}</p>
                <p v-if="result.credentials"><strong>A senha provisória aparece somente agora.</strong> Copie a mensagem antes de fechar. O aluno deverá trocar a senha ao entrar.</p>
                <p v-else>Se a pessoa esqueceu a senha ou a senha provisória não foi guardada, <RouterLink :to="`/alunos/${result.student.id}`" class="access-profile">abra o perfil do aluno para redefinir o acesso</RouterLink>.</p>
                <label>Mensagem individual<textarea readonly :value="message" rows="7" /></label>
                <button type="button" class="btn-primary" @click="copy">{{ copied ? 'Mensagem copiada' : 'Copiar mensagem' }}</button>
                <p>Envie individualmente pelo WhatsApp da WNFit. A cópia não confirma o envio.</p>
            </template>
            <template v-else-if="!registration.aluno_id">
                <p>Confira os cadastros antes de criar outro. Coincidências de telefone são sugestões; confirme o nome da pessoa.</p>
                <form class="access-search" @submit.prevent="search()">
                    <label>Buscar aluno por nome ou telefone<input v-model="query" maxlength="120" /></label>
                    <button class="btn-secondary" :disabled="saving || loading">Buscar</button>
                </form>
                <p v-if="loading" role="status">Buscando alunos…</p>
                <template v-else>
                    <p v-if="!candidates.data.length">Nenhum aluno encontrado.</p>
                    <label v-for="student in candidates.data" :key="student.id" class="access-option">
                        <input v-model="selected" type="radio" :value="student.id" :disabled="saving || student.linkedToAnotherParticipant" @change="creating = false" />
                        <span><strong>{{ student.name }}</strong><span>{{ student.phone || 'Sem telefone' }} {{ student.username ? `· @${student.username}` : '· Sem acesso' }}</span><small v-if="student.phoneMatch">Mesmo telefone da inscrição</small><small v-if="student.linkedToAnotherParticipant">Já vinculado a outro participante deste evento</small></span>
                    </label>
                    <div v-if="candidates.last_page > 1" class="access-actions"><button type="button" class="btn-secondary" :disabled="saving || candidates.current_page === 1" @click="search(candidates.current_page - 1)">Anterior</button><span>{{ candidates.current_page }} / {{ candidates.last_page }}</span><button type="button" class="btn-secondary" :disabled="saving || candidates.current_page === candidates.last_page" @click="search(candidates.current_page + 1)">Próxima</button></div>
                    <button type="button" class="btn-primary" :disabled="!selected || saving" @click="save('link')">Vincular aluno e preparar acesso</button>
                    <div class="access-create">
                        <button v-if="!creating" type="button" class="btn-secondary" :disabled="saving" @click="creating = true; selected = null">A pessoa ainda não é aluna</button>
                        <template v-else><p>Criar cadastro para <strong>{{ registration.name }}</strong>, com telefone {{ registration.phone }}. Este cadastro não cria assinatura nem cobrança.</p><button type="button" class="btn-primary" :disabled="saving" @click="save('create')">Criar participante e acesso</button></template>
                    </div>
                </template>
            </template>
            <p v-if="saving" role="status">Preparando acesso…</p>
            <button type="button" class="btn-secondary access-close" :disabled="saving" @click="close">Fechar</button>
        </section>
    </div>
</template>

<style scoped>
.access-profile{text-decoration:underline;color:var(--wn-primary-strong,#365314)}
.participant-access{margin-top:12px;width:100%;min-width:0}.participant-access p{font-size:13px;line-height:1.6;color:var(--wn-muted);margin:8px 0}.access-panel{padding:18px;border:1px solid var(--wn-line);border-radius:14px;background:var(--wn-surface-soft,#f8faf5);margin-top:12px}.access-panel h4{font-weight:700}.access-panel label{display:grid;gap:8px;font-size:13px;margin:12px 0}.access-panel input:not([type=radio]),.access-panel textarea{width:100%;min-width:0;padding:12px;border:1px solid var(--wn-line);border-radius:10px;background:white;font-size:16px}.access-search,.access-actions{display:flex;align-items:center;gap:12px}.access-search label{flex:1;min-width:0}.access-panel .access-option{grid-template-columns:20px minmax(0,1fr);align-items:start;padding:12px;background:white;border:1px solid var(--wn-line);border-radius:10px;cursor:pointer}.access-option input{margin-top:4px;accent-color:var(--wn-primary-strong,#365314)}.access-option span span,.access-option small{display:block;overflow-wrap:anywhere;margin-top:4px}.access-option small{color:var(--wn-primary-strong,#365314)}.access-create{border-top:1px solid var(--wn-line);margin-top:16px;padding-top:16px}.access-panel .access-error{color:#9f1239}.access-close{margin-top:16px}.access-actions{margin:12px 0;flex-wrap:wrap}@media(max-width:600px){.access-panel{padding:12px}.access-search{display:block}.access-panel button{min-height:44px;white-space:normal}}
</style>
