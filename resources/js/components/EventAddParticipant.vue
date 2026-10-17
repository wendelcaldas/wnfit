<script setup>
import { ref } from 'vue';
const props = defineProps({ eventId: Number });
const emit = defineEmits(['added']);
const open=ref(false), query=ref(''), people=ref({data:[]}), student=ref(null), creating=ref(false), name=ref(''), phone=ref(''), busy=ref(false), error=ref('');
function fail(e){error.value=Object.values(e.response?.data?.errors||{}).flat()[0]||e.response?.data?.message||'Não foi possível conectar.';}
async function search(page=1){busy.value=true;error.value='';try{people.value=(await window.axios.get(`/api/events/${props.eventId}/people`,{params:{q:query.value,page}})).data;}catch(e){fail(e);}finally{busy.value=false;}}
async function show(){open.value=true;await search();}
async function save(){busy.value=true;error.value='';try{await window.axios.post(`/api/events/${props.eventId}/registrations`,creating.value?{name:name.value,phone:phone.value||null}:{student_id:student.value});open.value=false;emit('added',props.eventId);}catch(e){fail(e);}finally{busy.value=false;}}
</script>
<template>
  <button v-if="!open" type="button" class="btn-primary" @click="show">Adicionar participante</button>
  <section v-else class="add-person">
    <h3>Adicionar participante</h3><p v-if="error" role="alert">{{error}}</p>
    <form @submit.prevent="search()"><label>Buscar na base<input v-model="query" maxlength="120" /></label><button class="btn-secondary" :disabled="busy">Buscar</button></form>
    <form @submit.prevent="save">
      <label v-for="p in people.data" :key="p.id" class="person-option"><input v-model="student" type="radio" :value="p.id" @change="creating=false" /><span>{{p.nome}} · {{p.telefone||'Sem telefone'}} · {{p.perfil_tipo==='aluno'?'Aluno':'Participante'}}</span></label>
      <div v-if="people.last_page>1"><button type="button" class="btn-secondary" :disabled="busy||people.current_page===1" @click="search(people.current_page-1)">Anterior</button><span>{{people.current_page}} / {{people.last_page}}</span><button type="button" class="btn-secondary" :disabled="busy||people.current_page===people.last_page" @click="search(people.current_page+1)">Próxima</button></div>
      <button type="button" class="btn-secondary" @click="creating=!creating;student=null">Cadastrar novo participante</button>
      <template v-if="creating"><p>Somente um perfil de participante, sem matrícula ou mensalidade.</p><label>Nome<input v-model="name" required minlength="2" maxlength="120" /></label><label>Telefone (opcional)<input v-model="phone" type="tel" maxlength="30" /></label></template>
      <button class="btn-primary" :disabled="busy||(!creating&&!student)">Confirmar inscrição</button><button type="button" class="btn-secondary" :disabled="busy" @click="open=false">Cancelar</button>
    </form><p>A inclusão pela equipe não registra consentimento de marketing ou aceite em nome da pessoa.</p>
  </section>
</template>
<style scoped>
.add-person{padding:18px;border:1px solid var(--wn-line);border-radius:14px;margin:16px 0}.add-person label{display:grid;gap:8px;margin:12px 0;font-size:14px}.add-person input:not([type=radio]){padding:12px;border:1px solid var(--wn-line);border-radius:10px;width:100%;font-size:16px}.add-person .person-option{display:flex;align-items:center;gap:12px;padding:12px;background:var(--wn-surface-soft)}.add-person button{margin:8px 8px 8px 0;min-height:44px}
</style>
