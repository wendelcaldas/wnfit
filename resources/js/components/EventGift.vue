<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { ArrowRight, Gift, X, Check } from 'lucide-vue-next';
const props = defineProps({ eventId: Number, autoOpenAllowed: Boolean, available: { type: Boolean, default: true } });
const loading = ref(true), result = ref(null), error = ref(''), busy = ref(false);
const dialog = ref(null), trigger = ref(null), canvas = ref(null), title = ref(null);
let drawing = false, distance = 0, last = null, autoOpened = false, disposed = false, previousFocus;
const base = () => '/api/student/events/' + props.eventId + '/gift';
function fail(e) { error.value = e.response?.data?.message || 'Não foi possível abrir seu presente. Tente novamente.'; }
function paint() {
    if (!canvas.value) return;
    const c = canvas.value, ctx = c.getContext('2d');
    c.width = c.clientWidth; c.height = 180;
    ctx.fillStyle = '#151715'; ctx.fillRect(0, 0, c.width, c.height);
    ctx.fillStyle = '#a3e635'; ctx.textAlign = 'center'; ctx.font = 'bold 18px sans-serif';
    ctx.fillText('Raspe para descobrir', c.width / 2, 96);
    distance = 0; drawing = false;
}
async function open() {
    if (!dialog.value || dialog.value.open) return;
    previousFocus = document.activeElement;
    dialog.value.showModal(); document.body.classList.add('event-gift-open');
    await nextTick(); paint();
}
function close() { dialog.value?.close(); }
function closed() {
    drawing = false; document.body.classList.remove('event-gift-open');
    const target = previousFocus instanceof HTMLElement && previousFocus !== document.body && previousFocus.isConnected ? previousFocus : trigger.value;
    target?.focus({ preventScroll: true });
}
function maybeOpen() {
    if (!disposed && !loading.value && !error.value && !result.value?.gift && props.autoOpenAllowed && !autoOpened) { autoOpened = true; open(); }
}
async function load() {
    if (!props.available) { loading.value = false; return; }
    loading.value = true; error.value = '';
    try { const { data } = await window.axios.get(base()); if (!disposed) result.value = data; }
    catch (e) { if (!disposed) fail(e); }
    finally { if (!disposed) { loading.value = false; maybeOpen(); } }
}
async function reveal() {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try { const { data } = await window.axios.post(base()); if (!disposed) { result.value = data; await nextTick(); title.value?.focus(); } }
    catch (e) { if (!disposed) fail(e); }
    finally { busy.value = false; }
}
function down(e) { drawing = true; last = [e.offsetX, e.offsetY]; e.target.setPointerCapture(e.pointerId); }
function move(e) {
    if (!drawing || busy.value) return;
    const point = [e.offsetX, e.offsetY], ctx = canvas.value.getContext('2d');
    ctx.globalCompositeOperation = 'destination-out'; ctx.lineWidth = 36; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(...last); ctx.lineTo(...point); ctx.stroke();
    distance += Math.hypot(point[0] - last[0], point[1] - last[1]); last = point;
    if (distance > 350) { drawing = false; reveal(); }
}
watch(() => props.autoOpenAllowed, maybeOpen);
onMounted(load);
onUnmounted(() => { disposed = true; dialog.value?.close(); document.body.classList.remove('event-gift-open'); });
</script>
<template>
    <section class="student-card gift-entry" :class="{ 'gift-entry-locked': !available }" aria-label="Meu presente" :aria-disabled="!available">
        <Gift class="gift-entry-icon" :size="28" aria-hidden="true" />
        <div class="gift-entry-copy">
            <p class="student-overline">MEU PRESENTE</p>
            <h2>{{ result?.gift ? result.gift.name : 'Tem um cuidado esperando por você' }}</h2>
            <p v-if="!available">Disponível no dia do evento.</p>
            <p v-else-if="loading" role="status">Carregando seu presente…</p>
            <p v-else-if="result?.gift">{{ result.deliveredAt ? 'Retirada confirmada pela equipe.' : 'Presente revelado. Retire com a equipe.' }}</p>
            <p v-else>Uma surpresa do encontro para levar com você.</p>
            <p v-if="error && !dialog?.open" role="alert">{{ error }} <button @click="load">Tentar novamente</button></p>
        </div>
        <button ref="trigger" class="gift-entry-button" :disabled="!available || loading || !!error" aria-haspopup="dialog" @click="open">
            {{ !available ? 'Bloqueado por enquanto' : result?.gift ? 'Ver meu presente' : 'Abrir raspadinha' }} <ArrowRight :size="18" aria-hidden="true" />
        </button>
    </section>
    <Teleport to="body">
        <dialog ref="dialog" class="event-gift-dialog student-app" aria-labelledby="gift-title" aria-describedby="gift-description" @close="closed" @click="e => { if (e.target === dialog) close(); }">
            <div class="gift-content">
                <button class="gift-close" aria-label="Fechar presente" autofocus @click="close"><X :size="22" aria-hidden="true" /></button>
                <span class="gift-symbol"><Gift :size="32" aria-hidden="true" /></span>
                <p class="student-overline">UM PRESENTE DO NOSSO ENCONTRO</p>
                <h2 id="gift-title" ref="title" tabindex="-1">{{ result?.gift ? result.gift.name : 'Esse carinho é seu' }}</h2>
                <p id="gift-description">{{ result?.gift ? result.gift.message : 'Raspe o cartão e descubra o presente que preparamos para você.' }}</p>
                <ActionFeedback :message="error" type="error" />
                <template v-if="result?.gift">
                    <div class="gift-pickup"><Check :size="22" aria-hidden="true" /><div><strong>{{ result.deliveredAt ? 'Retirada confirmada' : 'Seu presente está garantido' }}</strong><p v-if="!result.deliveredAt">{{ result.gift.instructions }}</p><p v-if="!result.deliveredAt">Mostre esta tela à equipe para retirar.</p></div></div>
                    <button class="btn-primary" @click="close">Voltar ao evento</button>
                </template>
                <template v-else>
                    <canvas ref="canvas" aria-hidden="true" @pointerdown="down" @pointermove="move" @pointerup="drawing=false" @pointercancel="drawing=false" />
                    <button class="btn-primary" :disabled="busy" @click="reveal">{{ busy ? 'Revelando…' : 'Revelar meu presente' }}</button>
                    <p class="gift-hint">Você também pode usar o botão para revelar.</p>
                </template>
            </div>
        </dialog>
    </Teleport>
</template>
<style>body.event-gift-open{overflow:hidden}</style>
<style scoped>
.gift-entry-locked{filter:grayscale(1);opacity:.58}
.gift-entry{display:flex;align-items:center;gap:18px;padding:24px!important;margin-top:24px}.gift-entry-icon{flex-shrink:0;color:var(--wn-primary-strong)}.gift-entry-copy{flex:1;min-width:0}.gift-entry h2{font-size:20px;font-weight:750;margin:8px 0}.gift-entry p{line-height:1.6;overflow-wrap:anywhere}.gift-entry-button{display:flex;align-items:center;justify-content:center;gap:10px;min-height:48px;font-weight:750;border-radius:12px;padding:12px 16px;background:var(--wn-primary);color:#151715}.gift-entry-button:disabled{opacity:.5}.event-gift-dialog{min-height:0;height:fit-content;position:fixed;inset:0;margin:auto;width:calc(100% - 32px);max-width:460px;max-height:calc(100dvh - 32px);padding:0;border:0;border-radius:24px;background:#fff;color:#151715;overflow:auto;box-shadow:0 24px 80px #0003}.event-gift-dialog::backdrop{background:#151715a6;backdrop-filter:blur(4px)}.gift-content{position:relative;padding:32px 24px;text-align:center}.gift-close{position:absolute;top:12px;right:12px;width:44px;height:44px;display:grid;place-items:center;border-radius:50%;background:#f5f6f4}.gift-symbol{display:grid;place-items:center;width:72px;height:72px;background:#ecfccb;border-radius:24px;margin:8px auto 24px}.gift-content h2{font-size:30px;line-height:1.2;font-weight:800;margin:12px 0}.gift-content p{line-height:1.65;white-space:pre-line;overflow-wrap:anywhere}.gift-content canvas{display:block;width:100%;height:180px;border-radius:16px;touch-action:none;margin:24px 0}.gift-content .btn-primary{width:100%;min-height:48px;margin-top:20px}.gift-hint{font-size:12px;color:#646d60;margin-top:12px}.gift-pickup{display:flex;gap:12px;text-align:left;background:#f5f6f4;border-radius:16px;padding:20px;margin-top:24px}.gift-pickup svg{flex-shrink:0;color:#365314}.gift-pickup p{margin-top:8px;font-size:14px}@media(max-width:600px){.gift-entry{flex-wrap:wrap;padding:20px!important;gap:14px}.gift-entry-copy{flex-basis:calc(100% - 50px)}.gift-entry-button{width:100%}}button:focus-visible{outline:2px solid #365314;outline-offset:4px}
</style>
