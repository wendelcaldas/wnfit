<template>
    <AppShell eyebrow="Configurações > Horários" title="Horários de funcionamento" description="Organize sua semana de atendimento.">
        <SettingsNavigation />
        <ActionFeedback :message="error" type="error" :action="load" />
        <section v-if="loading" class="panel-card py-12 text-center text-[var(--wn-muted)]">Carregando horários...</section>
        <form v-else-if="ready" class="hours-layout" @submit.prevent="save">
            <section class="panel-card hours-week">
                <header class="hours-heading">
                    <div>
                        <h2>Sua semana</h2>
                        <p>{{ form.enforced ? 'Horários fechados bloqueiam a agenda.' : 'Ative para definir o expediente.' }}</p>
                    </div>
                    <label class="hours-toggle">
                        <input
                            v-model="form.enforced"
                            :disabled="!canManage || saving"
                            type="checkbox"
                            aria-label="Aplicar horários na agenda"
                        />
                        <span></span>
                    </label>
                </header>
                <fieldset :disabled="!canManage || !form.enforced || saving" class="hours-days">
                    <div v-for="index in [1, 2, 3, 4, 5, 6, 0]" :key="index" class="hours-day">
                        <h3>{{ days[index] }}</h3>
                        <div class="hours-intervals">
                            <span v-if="!form.weeklyHours[index].length" class="hours-closed">Fechado</span>
                            <div v-for="(interval, position) in form.weeklyHours[index]" :key="position" class="hours-range">
                                <input
                                    v-model="interval.start"
                                    type="time"
                                    required
                                    :aria-label="'Abertura ' + days[index] + ', intervalo ' + (position + 1)"
                                />
                                <span>–</span>
                                <input
                                    v-model="interval.end"
                                    type="time"
                                    required
                                    :aria-label="'Fechamento ' + days[index] + ', intervalo ' + (position + 1)"
                                />
                                <button
                                    type="button"
                                    class="hours-icon"
                                    :aria-label="'Remover intervalo de ' + days[index]"
                                    @click="form.weeklyHours[index].splice(position, 1)"
                                >
                                    <X :size="14" />
                                </button>
                            </div>
                        </div>
                        <button
                            v-if="form.weeklyHours[index].length < 3"
                            type="button"
                            class="hours-icon hours-add"
                            :aria-label="'Adicionar intervalo em ' + days[index]"
                            title="Adicionar intervalo"
                            @click="addInterval(index)"
                        >
                            <Plus :size="17" />
                        </button>
                    </div>
                </fieldset>
                <footer class="hours-footnote">
                    <Clock3 :size="14" /><span>{{ form.timezone }} · Use + para incluir outro intervalo.</span>
                </footer>
            </section>
            <aside class="panel-card hours-booking">
                <header class="hours-heading">
                    <div class="hours-link-title">
                        <Link2 :size="19" />
                        <h2>Reservas por link</h2>
                    </div>
                    <span class="hours-hint">{{ bookingLinks.length }}/3</span>
                </header>
                <p class="hours-hint">Crie links diferentes para cada atendimento. Cada um tem sua duração e professor.</p>
                <p v-if="!form.enforced" class="hours-hint">Ative e salve o expediente para receber reservas.</p>
                <div class="hours-links">
                    <article v-for="link in bookingLinks" :key="link.id" class="hours-link-card">
                        <div class="hours-heading">
                            <h3>{{ link.title }}</h3>
                            <label class="hours-toggle"
                                ><input
                                    type="checkbox"
                                    :checked="link.active"
                                    :disabled="!canManage || linkSaving || saving || (!link.active && !form.enforced)"
                                    :aria-label="'Ativar link de ' + link.title"
                                    @change="toggleLink(link, $event)" /><span></span
                            ></label>
                        </div>
                        <p>{{ link.durationMinutes }} min · {{ instructorName(link.instructorId) }}</p>
                        <p v-if="removingId === link.id" class="hours-delete-warning" role="status">
                            O link deixará de funcionar. Os compromissos já agendados serão preservados.
                        </p>
                        <div class="hours-card-actions">
                            <button type="button" :disabled="!link.publicUrl || linkSaving" :class="{ 'copy-confirmed': copiedId === link.id }" @click="copyLink(link)">
                                <span class="copy-feedback" role="status" aria-live="polite">
                                    <Transition name="copy-label" mode="out-in">
                                        <span v-if="copiedId === link.id" key="copied" class="copy-label"><Check :size="14" />Copiado</span>
                                        <span v-else key="copy" class="copy-label"><Copy :size="14" />Copiar</span>
                                    </Transition>
                                </span></button
                            ><button v-if="canManage" type="button" :disabled="linkSaving || saving" @click="editLink(link)">
                                <Pencil :size="14" />Editar</button
                            ><button
                                v-if="canManage"
                                type="button"
                                :disabled="linkSaving || saving"
                                class="hours-remove"
                                @click="removeLink(link)"
                            >
                                <Trash2 :size="14" />{{ removingId === link.id ? 'Confirmar exclusão' : 'Excluir' }}
                            </button>
                        </div>
                        <div v-if="copyFallbackId === link.id" class="hours-share">
                            <input
                                :value="absoluteUrl(link.publicUrl)"
                                readonly
                                aria-label="Link para copiar"
                                @focus="$event.target.select()"
                            />
                        </div>
                    </article>
                </div>
                <button
                    v-if="canManage && !editorOpen && bookingLinks.length < 3"
                    class="btn-secondary hours-new-link"
                    :disabled="saving || linkSaving"
                    type="button"
                    @click="newLink"
                >
                    <Plus :size="16" />Novo link
                </button>

                <p v-if="!bookingLinks.length && !editorOpen" class="hours-hint">Nenhum link criado.</p>
            </aside>
            <footer class="hours-actions">
                <span>{{ canManage ? 'As alterações são aplicadas ao salvar.' : 'Somente gestores podem alterar os horários.' }}</span>
                <button v-if="canManage" :disabled="saving || linkSaving" class="btn-primary" type="submit">
                    {{ saving ? 'Salvando...' : 'Salvar alterações' }}
                </button>
            </footer>
        </form>

        <Teleport to="body">
            <dialog ref="editorDialog" class="hours-dialog" aria-labelledby="link-editor-title" @cancel.prevent="closeEditor" @click="onDialogClick">
                <header class="hours-dialog-heading">
                    <h2 id="link-editor-title">{{ editingLinkId ? 'Editar atendimento' : 'Novo atendimento' }}</h2>
                    <button type="button" class="hours-icon" :disabled="linkSaving" aria-label="Fechar" @click="closeEditor"><X :size="19" /></button>
                </header>
                <form @submit.prevent="saveLink">
                <fieldset
                    v-if="editorOpen"
                    :disabled="!canManage || saving || linkSaving"
                    class="hours-fields hours-link-editor"

                >

                    <label
                        >Atendimento<input
                            v-model.trim="draft.title"
                            required
                            class="form-control"
                            maxlength="120"
                            placeholder="Ex.: Aula experimental"
                    /></label>
                    <label
                        >Professor<select v-model="draft.instructorId" class="form-control" required>
                            <option :value="null">Selecione</option>
                            <option v-for="instructor in instructors" :key="instructor.id" :value="instructor.id">
                                {{ instructor.name }}
                            </option>
                        </select></label
                    >
                    <div class="hours-booking-row">
                        <label
                            >Duração<select v-model.number="draft.durationMinutes" class="form-control">
                                <option v-for="minutes in [15, 30, 45, 60, 90, 120]" :key="minutes" :value="minutes">
                                    {{ minutes }} min
                                </option>
                            </select></label
                        ><label
                            >Local<input v-model.trim="draft.location" class="form-control" maxlength="120" placeholder="Opcional"
                        /></label>
                    </div>
                    <label class="hours-draft-active"
                        ><input v-model="draft.active" type="checkbox" :disabled="!form.enforced" />Ativar este link</label
                    >
                    <div class="hours-editor-actions">
                        <button class="btn-secondary" type="button" @click="closeEditor">Cancelar</button
                        ><button class="btn-primary" type="submit">
                            {{ linkSaving ? 'Salvando...' : 'Salvar link' }}
                        </button>
                    </div>
                </fieldset>
                </form>
            </dialog>
        </Teleport>
    </AppShell>
</template>
<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Copy, X, Plus, Clock3, Link2, Pencil, Trash2, Check } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';
import SettingsNavigation from '../components/SettingsNavigation.vue';
import { notify } from '../composables/useNotifications';
const days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
const form = reactive({
    enforced: false,
    weeklyHours: Object.fromEntries(days.map((_, i) => [i, []])),
    timezone: 'America/Sao_Paulo',
});
const loading = ref(true),
    ready = ref(false),
    saving = ref(false),
    error = ref(''),
    canManage = ref(false),
    instructors = ref([]),
    bookingLinks = ref([]);
const editorOpen = ref(false),
    editingLinkId = ref(null),
    linkSaving = ref(false),
    removingId = ref(null),
    copyFallbackId = ref(null);
const blankDraft = () => ({ title: '', durationMinutes: 60, instructorId: null, location: '', active: false });
const draft = reactive(blankDraft());
const editorDialog = ref(null), copiedId = ref(null);
let copyTimer;
function closeEditor() {
    if (!linkSaving.value) editorOpen.value = false;
}
function onDialogClick(event) {
    if (event.target !== editorDialog.value) return;
    const rect = editorDialog.value.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeEditor();
}
watch(editorOpen, async (open) => {
    await nextTick();
    if (open && !editorDialog.value.open) editorDialog.value.showModal();
    else if (!open && editorDialog.value.open) editorDialog.value.close();
});
onBeforeUnmount(() => clearTimeout(copyTimer));

function assign(data) {
    const settings = data.settings;
    form.enforced = settings.enforced;
    form.timezone = settings.timezone;
    form.weeklyHours = Object.fromEntries(days.map((_, i) => [i, (settings.weeklyHours?.[i] || []).map((item) => ({ ...item }))]));
    bookingLinks.value = settings.bookingLinks || [];
    canManage.value = data.canManage;
    instructors.value = data.instructors || [];
}
function addInterval(index) {
    const ranges = form.weeklyHours[index];
    const previousEnd = ranges.at(-1)?.end;
    if (previousEnd && previousEnd >= '23:59') {
        notify('Ajuste o último intervalo para incluir outro.', { type: 'info' });
        return;
    }
    const start = previousEnd || '08:00';
    const [hour, minute] = start.split(':').map(Number);
    const endMinutes = Math.min(hour * 60 + minute + 60, 1439);
    const end = previousEnd
        ? String(Math.floor(endMinutes / 60)).padStart(2, '0') + ':' + String(endMinutes % 60).padStart(2, '0')
        : '18:00';
    ranges.push({ start, end });
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get('/api/organization/schedule-settings');
        assign(data);
        ready.value = true;
    } catch (e) {
        error.value = 'Não foi possível carregar os horários.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    saving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.put('/api/organization/schedule-settings', {
            enforced: form.enforced,
            weeklyHours: form.weeklyHours,
        });
        assign(data);
        notify('Horários de funcionamento atualizados.');
    } catch (e) {
        error.value =
            Object.values(e.response?.data?.errors || {})
                .flat()
                .join(' ') ||
            e.response?.data?.message ||
            'Não foi possível salvar os horários.';
    } finally {
        saving.value = false;
    }
}
function absoluteUrl(url) {
    return url ? new URL(url, window.location.origin).href : '';
}
function instructorName(id) {
    return instructors.value.find((instructor) => instructor.id === id)?.name || 'Sem professor';
}
function newLink() {
    editingLinkId.value = null;
    Object.assign(draft, blankDraft());
    editorOpen.value = true;
    removingId.value = null;
}
function editLink(link) {
    editingLinkId.value = link.id;
    Object.assign(draft, {
        title: link.title,
        durationMinutes: link.durationMinutes,
        instructorId: link.instructorId,
        location: link.location || '',
        active: link.active,
    });
    editorOpen.value = true;
    removingId.value = null;
}
function linkError(e) {
    error.value =
        Object.values(e.response?.data?.errors || {})
            .flat()
            .join(' ') ||
        e.response?.data?.message ||
        'Não foi possível atualizar o link.';
}
async function saveLink() {
    if (saving.value || linkSaving.value || !canManage.value) return;
    linkSaving.value = true;
    error.value = '';
    try {
        const endpoint = editingLinkId.value ? '/api/organization/booking-links/' + editingLinkId.value : '/api/organization/booking-links';
        const { data } = await window.axios[editingLinkId.value ? 'put' : 'post'](endpoint, { ...draft });
        assignLinks(data);
        editorOpen.value = false;
        notify('Link de atendimento salvo.');
    } catch (e) {
        linkError(e);
    } finally {
        linkSaving.value = false;
    }
}
function assignLinks(data) {
    bookingLinks.value = data.settings.bookingLinks || [];
    instructors.value = data.instructors || instructors.value;
}
async function toggleLink(link, event) {
    const checkbox = event.target;
    const active = checkbox.checked;
    linkSaving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.put('/api/organization/booking-links/' + link.id, {
            title: link.title,
            durationMinutes: link.durationMinutes,
            instructorId: link.instructorId,
            location: link.location,
            active,
        });
        assignLinks(data);
        notify(active ? 'Link ativado.' : 'Link desativado.');
    } catch (e) {
        linkError(e);
    } finally {
        checkbox.checked = Boolean(bookingLinks.value.find((item) => item.id === link.id)?.active);
        linkSaving.value = false;
    }
}
async function removeLink(link) {
    if (removingId.value !== link.id) {
        removingId.value = link.id;
        return;
    }
    linkSaving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.delete('/api/organization/booking-links/' + link.id);
        assignLinks(data);
        if (editingLinkId.value === link.id) editorOpen.value = false;
        notify('Link excluído.');
    } catch (e) {
        linkError(e);
    } finally {
        linkSaving.value = false;
        removingId.value = null;
    }
}
async function copyLink(link) {
    try {
        await navigator.clipboard.writeText(absoluteUrl(link.publicUrl));
        clearTimeout(copyTimer);
        copiedId.value = link.id;
        copyFallbackId.value = null;
        copyTimer = setTimeout(() => { copiedId.value = null; }, 3000);
    } catch {
        copyFallbackId.value = link.id;
        notify('Selecione e copie o link no campo do atendimento.', { type: 'info' });
    }
}
onMounted(load);
</script>

<style scoped>
.hours-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 20px;
    align-items: start;
}
.hours-week,
.hours-booking {
    padding: 22px;
}
.hours-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.hours-heading h2 {
    font-size: 15px;
    font-weight: 700;
}
.hours-heading p,
.hours-hint {
    font-size: 12px;
    line-height: 1.6;
    color: var(--wn-muted);
    margin-top: 5px;
}
.hours-toggle {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
}
.hours-toggle input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
}
.hours-toggle > span {
    width: 36px;
    height: 21px;
    border-radius: 20px;
    background: var(--wn-line);
    pointer-events: none;
}
.hours-toggle > span::after {
    content: '';
    display: block;
    width: 15px;
    height: 15px;
    margin: 3px;
    border-radius: 50%;
    background: white;
    box-shadow: 0 1px 3px #0002;
}
.hours-toggle input:checked + span {
    background: var(--wn-primary-strong);
}
.hours-toggle input:checked + span::after {
    transform: translateX(15px);
}
.hours-toggle input:focus-visible + span {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: 3px;
}
.hours-toggle input:disabled + span {
    opacity: 0.45;
}
.hours-days {
    margin-top: 22px;
}
.hours-days:disabled {
    opacity: 0.5;
}
.hours-day {
    display: grid;
    grid-template-columns: 145px minmax(0, 1fr) 28px;
    align-items: center;
    gap: 12px;
    min-height: 65px;
    padding: 12px 0;
    border-top: 1px solid var(--wn-line);
}
.hours-day h3 {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 14px;
    font-weight: 600;
}

.hours-intervals {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.hours-closed {
    color: var(--wn-muted);
    font-size: 12px;
}
.hours-range {
    display: flex;
    align-items: center;
    gap: 5px;
    border: 1px solid var(--wn-line);
    border-radius: 9px;
    padding: 5px 7px;
    font-size: 14px;
}
.hours-range input {
    width: 78px;
    min-width: 0;
    padding: 0;
    background: transparent;
    font-size: 14px;
    color: var(--wn-ink);
}
.hours-range input::-webkit-datetime-edit {
    padding: 0;
}
.hours-range input::-webkit-calendar-picker-indicator {
    margin: 0;
    padding: 0;
}
.hours-range input:focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    border-radius: 3px;
}
.hours-range > span {
    color: var(--wn-muted);
}
.hours-icon {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    color: var(--wn-muted);
    flex-shrink: 0;
}
.hours-icon:hover {
    background: var(--wn-surface-soft);
    color: var(--wn-ink);
}
.hours-footnote {
    display: flex;
    align-items: center;
    gap: 7px;
    border-top: 1px solid var(--wn-line);
    padding-top: 15px;
    font-size: 11px;
    color: var(--wn-muted);
}
.hours-link-title {
    display: flex;
    align-items: center;
    gap: 9px;
}
.hours-link-title svg {
    color: var(--wn-primary-strong);
}
.hours-hint {
    margin-top: 12px;
}
.hours-fields {
    display: grid;
    gap: 16px;
    margin-top: 22px;
}
.hours-fields label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    min-width: 0;
}
.hours-fields .form-control {
    font-size: 13px;
}
.hours-booking-row {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    gap: 12px;
}
.hours-share {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--wn-line);
}
.hours-share > span {
    font-size: 11px;
    color: var(--wn-muted);
}
.hours-share > div {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}
.hours-share input {
    width: 100%;
    min-width: 0;
    font-size: 12px;
    background: transparent;
    color: var(--wn-muted);
}
.hours-actions {
    grid-column: 1/-1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.hours-actions > span {
    font-size: 12px;
    color: var(--wn-muted);
}
@media (max-width: 1200px) {
    .hours-layout {
        grid-template-columns: minmax(0, 1fr);
    }
}
@media (max-width: 600px) {
    .hours-week,
    .hours-booking {
        padding: 16px;
    }
    .hours-day {
        grid-template-columns: minmax(0, 1fr) 28px;
        gap: 8px;
    }
    .hours-day h3 {
        grid-column: 1;
    }
    .hours-intervals {
        grid-column: 1;
        grid-row: 2;
    }
    .hours-day > .hours-add {
        grid-column: 2;
        grid-row: 1/3;
    }
    .hours-actions {
        align-items: stretch;
        flex-direction: column;
    }
    .hours-actions .btn-primary {
        justify-content: center;
    }
}
.hours-links {
    display: grid;
    gap: 12px;
    margin-top: 18px;
}
.hours-link-card {
    border: 1px solid var(--wn-line);
    border-radius: 12px;
    padding: 14px;
}
.hours-link-card h3,
.hours-link-editor h3 {
    font-size: 14px;
    font-weight: 700;
}
.hours-link-card p {
    font-size: 13px;
    color: var(--wn-muted);
    margin-top: 8px;
}
.hours-link-status {
    font-size: 11px;
    color: var(--wn-muted);
}
.hours-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 14px;
}
.hours-card-actions button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--wn-muted);
}
.hours-delete-warning {
    font-size: 12px;
    line-height: 1.5;
    color: #b91c1c;
}
.hours-card-actions .hours-remove {
    color: #b91c1c;
}
.hours-new-link {
    width: 100%;
    justify-content: center;
    margin-top: 16px;
}
.hours-link-editor {
    padding-top: 18px;
    border-top: 1px solid var(--wn-line);
}
.hours-fields .hours-draft-active {
    flex-direction: row;
    align-items: center;
    gap: 9px;
}
.hours-editor-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.hours-editor-actions button {
    font-size: 12px;
    padding: 9px 12px;
}

.hours-link-card {
    background: var(--wn-surface, white);
    color: var(--wn-ink);
    border-color: var(--wn-line);
    padding: 12px 14px;
}
.hours-link-card p { margin-top: 4px; color: var(--wn-muted); }
.hours-link-card .hours-card-actions { margin-top: 10px; }
.hours-link-card .hours-card-actions button { color: var(--wn-muted); transition: color 550ms ease; }
.hours-link-card .hours-card-actions .hours-remove,
.hours-link-card .hours-delete-warning { color: #b91c1c; }
.hours-link-card .hours-card-actions .copy-confirmed { color: var(--wn-primary-strong); }
.hours-link-card .hours-share input { color: var(--wn-ink); }
.copy-feedback { display: inline-grid; width: 70px; text-align: left; }
.copy-label { display: inline-flex; align-items: center; gap: 5px; }
.copy-label-enter-active, .copy-label-leave-active { transition: opacity 250ms ease, transform 250ms ease; }
.copy-label-enter-from { opacity: 0; transform: translateY(3px); }
.copy-label-leave-to { opacity: 0; transform: translateY(-3px); }
@media (prefers-reduced-motion: reduce) {
    .copy-label-enter-active, .copy-label-leave-active, .hours-link-card .hours-card-actions button { transition: none; }
}

.hours-dialog { border: 1px solid var(--wn-line); border-radius: 20px; background: var(--wn-surface, white); color: var(--wn-ink); padding: 24px; width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 48px); overflow-y:auto; margin:auto; box-shadow: 0 24px 80px #0003; }
.hours-dialog::backdrop { background: #101c185e; backdrop-filter: blur(3px); }
.hours-dialog-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; }
.hours-dialog-heading h2 { font-size:18px; font-weight:700; }
.hours-dialog .hours-link-editor { border:0; padding-top:0; margin-top:20px; }
</style>
