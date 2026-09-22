<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
const route = useRoute();
const event = ref(null), participant = ref(null), error = ref(''), busy = ref(false), recovery = ref(''), recoverMode = ref(false), code = ref(''), notice = ref('');
const form = ref({ name: '', phone: '', privacy: false, marketing: false });
const review = ref({ rating: '', feedback: '' });
const base = `/api/public/events/${encodeURIComponent(route.params.slug)}`;
let timer; let reviewLoaded = false;
function failure(e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Não foi possível conectar. Verifique a internet e tente novamente.'; }
async function load() { try { const { data } = await window.axios.get(base); event.value = data.event; participant.value = data.participant; if(!reviewLoaded && data.participant){review.value = {rating: data.participant.rating || '', feedback: data.participant.feedback || ''}; reviewLoaded = true;} } catch(e) { failure(e); } }
async function act(action, data) { busy.value = true; error.value = ''; notice.value = ''; try { const r = await window.axios.post(`${base}/${action}`, data); participant.value = r.data.participant; if(r.data.recoveryCode) recovery.value = r.data.recoveryCode; if(action === 'feedback') notice.value = 'Avaliação salva. Obrigado por participar!'; await load(); } catch(e) { failure(e); } finally { busy.value = false; } }
function date(value, time = false) { return value ? new Intl.DateTimeFormat('pt-BR', { timeZone: event.value.timezone, ...(time ? {hour:'2-digit',minute:'2-digit'} : {day:'numeric',month:'long',year:'numeric'}) }).format(new Date(value)) : 'A confirmar'; }
function saveAccess() { const blob = new Blob([`Meu evento WNFIT: ${event.value.name}\n${location.href}\nCódigo de recuperação: ${recovery.value}\nGuarde este código em segurança. Ele dá acesso à sua inscrição.`], {type:'text/plain;charset=utf-8'}); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'meu-evento-wnfit.txt'; a.click(); URL.revokeObjectURL(url); }
onMounted(() => { load(); timer = setInterval(load, 60000); });
onUnmounted(() => clearInterval(timer));
</script>

<template>
  <main class="event-public">
    <header class="event-brand"><strong>WN<span>FIT</span></strong><span>Movimento. Encontro. Comunidade.</span></header>
    <p v-if="error" class="event-error" role="alert">{{ error }} <button @click="error = ''; load()">Tentar novamente</button></p>
    <template v-if="event">
      <section class="event-hero">
        <p class="event-kicker">UMA EXPERIÊNCIA {{ event.organizer }}</p>
        <h1>{{ event.name }}</h1><p>{{ event.description }}</p>
        <div class="event-details"><div><small>QUANDO</small><strong>{{ date(event.startsAt) }}</strong><span>{{ date(event.startsAt, true) }} às {{ date(event.endsAt, true) }} · término previsto</span></div><div><small>ONDE</small><strong>{{ event.location }}</strong></div></div>
      </section>
      <section class="event-card">
        <p v-if="event.status === 'draft'" class="event-note">Prévia do gestor · este evento ainda não foi publicado.</p>
        <template v-if="event.status === 'cancelled'"><h2>Evento cancelado</h2><p>Entre em contato com a equipe organizadora para mais informações.</p></template>
        <template v-else-if="participant">
          <p class="event-kicker">MEU EVENTO</p><h2>Você está na lista, {{ participant.name.split(' ')[0] }}!</h2>
          <p v-if="!event.finished && !participant.checkedInAt">Sua pré-inscrição está confirmada. No dia, abra este mesmo link e confirme sua presença.</p>
          <div v-if="recovery" class="event-access"><strong>Guarde seu acesso</strong><p>O acesso fica salvo neste navegador. Para usar outro aparelho ou recuperar o acesso, guarde este código. Não compartilhe com outras pessoas.</p><code>{{ recovery }}</code><button class="event-secondary" @click="saveAccess">Baixar meu acesso e link</button></div>
          <div v-if="participant.checkedInAt" class="event-note">✓ Presença {{ participant.attendanceSource === 'self' ? 'confirmada por você' : 'registrada pela equipe' }}.</div>
          <template v-else-if="!event.finished"><button v-if="event.checkinOpen" class="event-primary" :disabled="busy" @click="act('checkin', {})">Estou no aulão · confirmar presença</button><p v-else class="event-note">A confirmação de presença abre em {{ date(event.checkinOpensAt) }}, às {{ date(event.checkinOpensAt, true) }}.</p><small>A confirmação é feita por você e pode ser revisada pela equipe. É preciso estar conectado à internet.</small></template>
          <template v-if="event.finished"><h3>Obrigado por fazer parte!</h3><p>O evento foi encerrado pela equipe.</p><p v-if="event.afterContent" class="event-multiline">{{ event.afterContent }}</p><form v-if="participant.checkedInAt" @submit.prevent="act('feedback', review)"><h3>{{ participant.rating ? 'Sua avaliação foi recebida. Quer atualizar?' : 'Como foi a experiência?' }}</h3><label>Nota<select v-model="review.rating" required><option disabled value="">Escolha sua nota</option><option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option></select></label><label>Conte para a equipe (opcional)<textarea v-model="review.feedback" maxlength="1000" /></label><button class="event-primary" :disabled="busy">Salvar avaliação</button></form></template>
        </template>
        <template v-else>
          <h2>{{ recoverMode ? 'Volte para o seu evento' : 'Seu próximo encontro começa aqui' }}</h2>
          <form v-if="recoverMode" @submit.prevent="act('recover', {code})"><p>Digite o código que recebeu ao se inscrever.</p><label>Código de recuperação<input v-model="code" required maxlength="40" autocomplete="off" /></label><button class="event-primary" :disabled="busy">Acessar minha inscrição</button></form>
          <form v-else-if="event.registrationOpen" @submit.prevent="act('register', form)"><p>Inscreva-se com seus dados. É rápido e não precisa instalar nada.</p><label>Seu nome<input v-model="form.name" required minlength="2" maxlength="120" autocomplete="name" /></label><label>WhatsApp com DDD<input v-model="form.phone" required type="tel" inputmode="tel" autocomplete="tel" placeholder="(71) 99999-9999" maxlength="30" /></label><label class="event-check"><input v-model="form.privacy" type="checkbox" required /><span>Concordo com o uso do meu nome e telefone pela {{ event.organizer }} e pela WNFIT para organizar minha inscrição, presença e avaliação neste evento. Para corrigir ou excluir meus dados, posso procurar a equipe organizadora.</span></label><label class="event-check"><input v-model="form.marketing" type="checkbox" /><span>Quero receber novidades e convites da organização (opcional).</span></label><button class="event-primary" :disabled="busy">{{ busy ? 'Salvando…' : 'Quero participar' }}</button></form>
          <p v-else>As inscrições não estão abertas neste momento.</p>
          <button class="event-secondary" @click="recoverMode = !recoverMode">{{ recoverMode ? 'Voltar para inscrição' : 'Já me inscrevi · recuperar acesso' }}</button>
        </template>
        <p v-if="notice" role="status" class="event-note">{{ notice }}</p>
      </section>
      <section v-if="event.instructions" class="event-card"><h2>Prepare-se para o encontro</h2><p class="event-multiline">{{ event.instructions }}</p></section>
      <footer>Promovido por {{ event.organizer }} · experiência com WNFIT</footer>
    </template><p v-else-if="!error">Carregando seu evento…</p>
  </main>
</template>

<style scoped>
.event-public{min-height:100vh;background:#f4f5ed;color:#163c36;padding:24px 20px 40px}.event-public>*{max-width:640px;margin-left:auto;margin-right:auto}.event-brand{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:32px}.event-brand strong{font-size:26px}.event-brand strong span{color:#618643}.event-brand>span{font-size:11px;max-width:150px;text-align:right}.event-hero{background:linear-gradient(145deg,#123f3a,#286b60);border-radius:28px;padding:32px 26px;color:#fff;position:relative;overflow:hidden}.event-kicker{font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase}.event-hero h1{font-size:clamp(32px,7vw,48px);font-weight:800;line-height:1.12;margin:18px 0}.event-hero>p{line-height:1.7}.event-details{display:grid;gap:20px;margin-top:28px;border-top:1px solid #ffffff30;padding-top:24px}.event-details div{display:grid;gap:5px}.event-details small{font-size:10px;letter-spacing:2px;color:#d5e6a8}.event-details span{font-size:13px}.event-card{background:white;border:1px solid #dce4db;border-radius:24px;padding:26px;margin-top:18px}.event-card h2{font-size:24px;line-height:1.3;font-weight:750;margin-bottom:12px}.event-card h3{font-size:18px;font-weight:700;margin:20px 0 12px}.event-card p{font-size:14px;line-height:1.7;margin:12px 0}.event-card label{display:grid;gap:7px;font-size:14px;margin:18px 0}.event-card input:not([type=checkbox]),textarea,select{width:100%;border:1px solid #b7c9c0;border-radius:12px;padding:13px;font-size:16px;background:#fff;color:#163c36}.event-check{display:flex!important;align-items:flex-start;font-size:12px!important;line-height:1.6}.event-check input{margin-top:4px;width:18px;height:18px;flex-shrink:0;accent-color:#215f48}.event-primary,.event-secondary{display:block;width:100%;padding:15px;border-radius:14px;font-weight:700;font-size:14px;margin-top:16px;cursor:pointer}.event-primary{background:#d6ee8c;color:#173b2c}.event-secondary{border:1px solid #c9d5c9;background:white}.event-primary:disabled{opacity:.5}.event-note,.event-access{padding:16px;background:#f0f6e7;border-radius:14px;margin:18px 0!important}.event-access code{display:block;overflow-wrap:anywhere;font-weight:bold;font-size:16px}.event-error{background:#fee4dc;padding:16px;border-radius:12px;margin-bottom:20px}.event-error button{text-decoration:underline}.event-multiline{white-space:pre-line}.event-card small{display:block;font-size:12px;line-height:1.6}footer{text-align:center;padding-top:30px;font-size:11px}
</style>
