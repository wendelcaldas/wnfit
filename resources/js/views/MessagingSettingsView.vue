<template>
    <AppShell
        eyebrow="Configuracoes > Mensagens"
        title="Mensagens e cobrancas"
        description="Configure o numero WhatsApp do studio e os textos enviados aos alunos."
        search-placeholder="Buscar configuracoes..."
    >
        <div class="grid gap-5 xl:grid-cols-[1fr_390px]">
            <form class="panel-card" @submit.prevent="saveSettings">
                <div class="flex flex-col gap-4 border-b border-[var(--wn-line)] pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-[var(--wn-ink)]">Configuracao do WhatsApp</h2>
                        <p class="mt-1 text-sm text-[var(--wn-muted)]">Use o numero aprovado no Twilio para este studio.</p>
                    </div>
                    <button class="btn-primary justify-center" :disabled="saving">
                        {{ saving ? 'Salvando...' : 'Salvar configuracao' }}
                    </button>
                </div>

                <div class="mt-6 grid gap-5">
                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Numero WhatsApp remetente</span>
                        <input
                            v-model.trim="form.whatsapp_from"
                            class="form-control"
                            placeholder="+5571999990000"
                            maxlength="40"
                        />
                    </label>

                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Content Template SID de cobranca aprovado no Twilio</span>
                        <input
                            v-model.trim="form.charge_template_sid"
                            class="form-control"
                            placeholder="Opcional durante testes no sandbox"
                            maxlength="120"
                        />
                    </label>

                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Content Template SID de boas-vindas aprovado no Twilio</span>
                        <input
                            v-model.trim="form.welcome_template_sid"
                            class="form-control"
                            placeholder="Opcional durante testes no sandbox"
                            maxlength="120"
                        />
                    </label>

                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Mensagem de boas-vindas ao novo aluno</span>
                        <textarea
                            v-model="form.welcome_message_body"
                            class="form-control min-h-36"
                            maxlength="1000"
                            required
                            @focus="activeTemplate = 'welcome'"
                        />
                    </label>

                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-[var(--wn-muted)]">Mensagem padrao de cobranca</span>
                        <textarea
                            v-model="form.charge_message_body"
                            class="form-control min-h-44"
                            maxlength="1000"
                            required
                            @focus="activeTemplate = 'charge'"
                        />
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-[var(--wn-line)] bg-white p-4">
                        <input v-model="form.ativo" type="checkbox" class="h-4 w-4" />
                        <span>
                            <span class="block text-sm font-semibold text-[var(--wn-ink)]">Envio de mensagens ativo</span>
                            <span class="mt-1 block text-sm text-[var(--wn-muted)]">Quando ativo, as cobrancas podem ser enviadas pelo WhatsApp.</span>
                        </span>
                    </label>

                    <p v-if="message" class="rounded-lg px-4 py-3 text-sm" :class="messageType === 'error' ? 'bg-rose-50 text-rose-600' : 'bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]'">
                        {{ message }}
                    </p>
                </div>
            </form>

            <aside class="space-y-5">
                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Producao</h2>
                            <p class="text-sm text-[var(--wn-muted)]">{{ readiness.ready ? 'Pronto para envio real.' : 'Itens pendentes para envio real.' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <div
                            v-for="check in readiness.checks"
                            :key="check.key"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="check.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-semibold">{{ check.label }}</span>
                                <CheckCircle2 v-if="check.ok" class="h-4 w-4 shrink-0" />
                                <AlertTriangle v-else class="h-4 w-4 shrink-0" />
                            </div>
                            <p v-if="!check.ok" class="mt-1 text-xs">{{ check.hint }}</p>
                        </div>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-[var(--wn-primary-soft)] text-[var(--wn-primary-strong)]">
                            <MessageSquareText class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">Preview</h2>
                            <p class="text-sm text-[var(--wn-muted)]">Exemplo com dados ficticios.</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-3">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--wn-muted)]">Boas-vindas</p>
                            <div class="rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4 text-sm leading-6 text-[var(--wn-ink)]">
                                {{ welcomePreview }}
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--wn-muted)]">Cobranca</p>
                            <div class="rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4 text-sm leading-6 text-[var(--wn-ink)]">
                                {{ chargePreview }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel-card">
                    <h2 class="text-lg font-semibold">Variaveis disponiveis</h2>
                    <p class="mt-1 text-sm text-[var(--wn-muted)]">Clique para inserir no template em edicao.</p>
                    <div class="mt-4 grid gap-2">
                        <button
                            v-for="variable in variables"
                            :key="variable.key"
                            type="button"
                            class="flex items-center justify-between rounded-lg border border-[var(--wn-line)] bg-white px-3 py-2 text-left text-sm transition hover:border-[var(--wn-primary-strong)]"
                            @click="appendVariable(variable.key)"
                        >
                            <span class="font-mono text-[var(--wn-primary-strong)]">{{ variable.key }}</span>
                            <span class="text-xs text-[var(--wn-muted)]">{{ variable.label }}</span>
                        </button>
                    </div>
                </section>
            </aside>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { AlertTriangle, CheckCircle2, MessageSquareText, ShieldCheck } from 'lucide-vue-next';
import AppShell from '../components/AppShell.vue';

const loading = ref(true);
const saving = ref(false);
const message = ref('');
const messageType = ref('success');
const activeTemplate = ref('welcome');
const variables = ref([]);
const serverPreviews = reactive({ charge: '', welcome: '' });
const readiness = reactive({ ready: false, checks: [] });
const form = reactive({
    whatsapp_from: '',
    charge_template_sid: '',
    charge_message_body: '',
    welcome_template_sid: '',
    welcome_message_body: '',
    ativo: true,
});

const renderPreview = (template) => (template || '')
    .replaceAll('{aluno}', 'Mariana Alves')
    .replaceAll('{plano}', 'Plano Mensal')
    .replaceAll('{valor}', 'R$ 150,00')
    .replaceAll('{vencimento}', '10/08/2026')
    .replaceAll('{link_pagamento}', 'https://pay.wnfit.test/exemplo')
    .replaceAll('{studio}', 'Studio Teste WNFit');

const chargePreview = computed(() => {
    const template = form.charge_message_body || serverPreviews.charge;
    return renderPreview(template);
});

const welcomePreview = computed(() => {
    const template = form.welcome_message_body || serverPreviews.welcome;
    return renderPreview(template);
});

const loadSettings = async () => {
    const { data } = await window.axios.get('/api/organization/messaging');
    Object.assign(form, {
        whatsapp_from: data.settings.whatsappFrom ?? '',
        charge_template_sid: data.settings.chargeTemplateSid ?? '',
        charge_message_body: data.settings.chargeMessageBody,
        welcome_template_sid: data.settings.welcomeTemplateSid ?? '',
        welcome_message_body: data.settings.welcomeMessageBody,
        ativo: data.settings.active,
    });
    variables.value = data.variables;
    Object.assign(serverPreviews, data.previews ?? { charge: data.preview, welcome: '' });
    Object.assign(readiness, data.readiness ?? { ready: false, checks: [] });
};

const saveSettings = async () => {
    saving.value = true;
    message.value = '';
    try {
        const { data } = await window.axios.patch('/api/organization/messaging', form);
        Object.assign(form, {
            whatsapp_from: data.settings.whatsappFrom ?? '',
            charge_template_sid: data.settings.chargeTemplateSid ?? '',
            charge_message_body: data.settings.chargeMessageBody,
            welcome_template_sid: data.settings.welcomeTemplateSid ?? '',
            welcome_message_body: data.settings.welcomeMessageBody,
            ativo: data.settings.active,
        });
        Object.assign(serverPreviews, data.previews ?? { charge: data.preview, welcome: '' });
        Object.assign(readiness, data.readiness ?? { ready: false, checks: [] });
        messageType.value = 'success';
        message.value = 'Configuracao salva com sucesso.';
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        messageType.value = 'error';
        message.value = errors ? Object.values(errors).flat()[0] : 'Nao foi possivel salvar a configuracao.';
    } finally {
        saving.value = false;
    }
};

const appendVariable = (key) => {
    const field = activeTemplate.value === 'charge' ? 'charge_message_body' : 'welcome_message_body';
    form[field] = `${form[field]}${form[field].endsWith(' ') ? '' : ' '}${key}`;
};

onMounted(async () => {
    try {
        await loadSettings();
    } finally {
        loading.value = false;
    }
});
</script>
