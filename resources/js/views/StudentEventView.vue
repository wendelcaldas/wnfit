<script setup>
import {ref,onMounted,watch} from 'vue';
import {RouterLink,useRoute} from 'vue-router';
import EventAlbum from '../components/EventAlbum.vue';
import EventGift from '../components/EventGift.vue';
import StudentShell from '../components/StudentShell.vue';
const route=useRoute(),events=ref([]),detail=ref(null),error=ref(''),busy=ref(false);
function fail(e){error.value=Object.values(e.response?.data?.errors||{}).flat()[0]||e.response?.data?.message||'Não foi possível carregar. Tente novamente.';}
async function load(){error.value='';detail.value=null;try{if(route.params.id)detail.value=(await window.axios.get('/api/student/events/'+route.params.id)).data;else events.value=(await window.axios.get('/api/student/events')).data;}catch(e){fail(e);}}
async function welcome(){busy.value=true;try{await window.axios.post('/api/student/events/'+route.params.id+'/welcome');detail.value.welcomed=true;}catch(e){fail(e);}finally{busy.value=false;}}
async function checkin(){busy.value=true;try{detail.value.participant=(await window.axios.post('/api/student/events/'+route.params.id+'/checkin')).data.participant;}catch(e){fail(e);}finally{busy.value=false;}}
function date(v){return v?new Date(v).toLocaleString('pt-BR',{timeZone:'America/Bahia',dateStyle:'medium',timeStyle:'short'}):'A confirmar';}
onMounted(load);watch(()=>route.params.id,load);
</script>
<template><StudentShell><p class="student-overline">MOVIMENTO E COMUNIDADE</p><h1 class="mt-2 text-3xl font-bold">{{detail?.event.name||'Meus eventos'}}</h1><p v-if="error" role="alert" class="student-error">{{error}} <button @click="load">Tentar novamente</button></p>
<template v-if="!route.params.id"><p v-if="!events.length" class="mt-5">Seus próximos encontros aparecerão aqui após a inscrição.</p><RouterLink v-for="event in events" :key="event.id" :to="'/aluno/evento/'+event.id" class="student-card event-student-card"><span>{{event.organizer}}</span><h2>{{event.name}}</h2><p>{{date(event.startsAt)}} · {{event.location}}</p><strong>Abrir experiência →</strong></RouterLink></template>
<template v-if="detail"><RouterLink to="/aluno/evento" class="event-back">← Meus eventos</RouterLink><section v-if="!detail.welcomed" class="student-card event-welcome" role="dialog" aria-labelledby="event-welcome-title"><p class="student-overline">BEM-VINDO À EXPERIÊNCIA</p><h2 id="event-welcome-title">{{detail.event.name}}</h2><p>Um encontro para se movimentar, compartilhar e cuidar de você.</p><button class="btn-primary" :disabled="busy" @click="welcome">Começar experiência</button></section><section class="student-card event-student-card"><p>{{detail.event.description}}</p><p>{{date(detail.event.startsAt)}} · {{detail.event.location}}</p><p class="event-instructions">{{detail.event.instructions}}</p><p v-if="detail.participant.checkedInAt">✓ Presença confirmada</p><button v-else-if="detail.event.checkinOpen" class="btn-primary" :disabled="busy" @click="checkin">Confirmar minha presença</button><p v-if="detail.event.finished">Evento encerrado. Obrigado por participar!</p></section>
<EventGift v-if="detail.event.modules.gift.enabled" :key="detail.event.id" :event-id="detail.event.id" />
<EventAlbum v-if="detail.event.modules.album.enabled" :key="detail.event.id" :event-id="detail.event.id" :instructions="detail.event.modules.album.instructions" />
<!-- event-modules -->
<RouterLink to="/aluno/treinos" class="student-card event-student-card"><h2>Meu treino</h2><p>Acompanhe sua ficha e continue sua rotina.</p></RouterLink></template></StudentShell></template>
<style scoped>.event-student-card{display:block;margin-top:20px;padding:24px!important}.event-student-card h2,.event-welcome h2{font-size:22px;font-weight:750;margin:8px 0}.event-student-card p,.event-welcome p{line-height:1.7;margin:12px 0;overflow-wrap:anywhere}.event-student-card strong{color:var(--wn-primary-strong)}.event-back{display:block;margin:16px 0;text-decoration:underline}.event-welcome{padding:24px!important;margin:20px 0;border:2px solid var(--wn-primary)}.event-instructions{white-space:pre-line}</style>
