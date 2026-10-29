<script setup>
import { computed, ref } from 'vue';
import { Star, MessageCircle } from 'lucide-vue-next';
const props = defineProps({ event: Object, participant: Object });
const emit = defineEmits(['updated']);
const rating = ref(props.participant.rating || ''), feedback = ref(props.participant.feedback || ''), busy = ref(false), error = ref(''), notice = ref('');
const available = computed(() => props.event.modulesAvailable && (props.event.pilotAccess || (props.event.finished && !!props.participant.checkedInAt)));
async function save() {
    if (!available.value || busy.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const { data } = await window.axios.post('/api/student/events/' + props.event.id + '/feedback', { rating: rating.value, feedback: feedback.value });
        emit('updated', data.participant); notice.value = 'Avaliação salva. Obrigado por participar!';
    } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Não foi possível salvar.'; }
    finally { busy.value = false; }
}
</script>
<template>
    <section class="student-card event-feedback" :class="{ 'event-module-locked': !event.modulesAvailable }" :aria-disabled="!event.modulesAvailable">
        <p class="student-overline"><MessageCircle :size="16" aria-hidden="true" /> SUA OPINIÃO IMPORTA</p>
        <h2>{{ event.modules.feedback.title || 'O que você achou do evento?' }}</h2>
        <p>{{ event.modules.feedback.message }}</p>
        <p v-if="!event.modulesAvailable" id="feedback-availability" class="feedback-availability">Avaliação bloqueada até o dia do evento.</p>
        <p v-else-if="!event.pilotAccess && !event.finished" id="feedback-availability" class="feedback-availability">Você poderá avaliar quando a equipe encerrar o evento.</p>
        <p v-else-if="!event.pilotAccess && !participant.checkedInAt" id="feedback-availability" class="feedback-availability">Confirme sua presença com a equipe para avaliar.</p>
        <form @submit.prevent="save">
            <fieldset :disabled="!available || busy" :aria-describedby="!available ? 'feedback-availability' : undefined">
                <legend class="rating-legend">Como foi sua experiência?</legend>
                <div class="rating-stars">
                    <label v-for="n in 5" :key="n">
                        <input v-model="rating" type="radio" :value="n" :name="'rating-'+event.id" :aria-label="n === 1 ? '1 estrela' : n+' estrelas'" required />
                        <span :class="{ filled: Number(rating) >= n }"><Star :size="30" :stroke-width="1.5" aria-hidden="true" /></span>
                    </label>
                </div>
                <p class="rating-caption" aria-live="polite">{{ rating ? rating + ' de 5 estrelas' : 'Escolha de 1 a 5 estrelas' }}</p>
                <label class="comment-label">Quer contar um pouco mais? <span>Comentário opcional</span><textarea v-model="feedback" maxlength="1000" rows="3" placeholder="O que você mais gostou? O que podemos melhorar?" /></label>
            </fieldset>
            <ActionFeedback :message="error" type="error" /><ActionFeedback v-model="notice" />
            <button class="btn-primary" :disabled="!available || busy || !rating">{{ busy ? 'Salvando…' : participant.rating ? 'Atualizar avaliação' : 'Enviar avaliação' }}</button>
        </form>
    </section>
</template>
<style scoped>
.event-module-locked{filter:grayscale(1);opacity:.58}
.event-feedback{padding:28px!important;margin-top:24px}.event-feedback .student-overline{display:flex;align-items:center;gap:8px}.event-feedback h2{font-size:24px;font-weight:750;margin:12px 0}.event-feedback p{margin:12px 0;line-height:1.7}.event-feedback textarea{width:100%;border:1px solid var(--wn-line);border-radius:12px;padding:12px;font-size:16px;background:#fafbf9;resize:vertical}.comment-label{display:grid;gap:8px;margin:24px 0 16px;font-weight:650}.comment-label span{font-size:12px;color:var(--wn-muted);font-weight:400}.rating-stars{display:flex;gap:8px}.rating-stars label{position:relative}.rating-stars input{position:absolute;opacity:0;width:100%;height:100%}.rating-stars span{display:grid;place-items:center;width:48px;height:48px;border:1px solid var(--wn-line);border-radius:12px;cursor:pointer;color:#646d60}.rating-stars .filled{color:#365314;background:#ecfccb;border-color:#a3e635}.rating-stars .filled svg{fill:#a3e635}.rating-stars input:focus-visible+span{outline:2px solid var(--wn-ink);outline-offset:3px}.rating-caption{font-size:13px;color:var(--wn-muted)}.rating-legend{font-weight:650;margin-bottom:12px}.feedback-availability{padding:12px 16px;background:#f5f6f4;border-radius:12px;font-size:14px}.event-feedback fieldset:disabled{opacity:.6}.event-feedback button{min-height:48px}.event-feedback button:disabled{opacity:.5}@media(max-width:480px){.event-feedback{padding:20px!important}.rating-stars{gap:6px}.rating-stars span{width:44px;height:44px}}
</style>
