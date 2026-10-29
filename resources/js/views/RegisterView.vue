<template>
    <div class="student-login-page auth-login-ui auth-register-page">
        <AuthShowcase />

        <main class="student-login-form">
            <div class="auth-register-card">
                <div class="register-topbar flex items-center justify-between gap-4">
                    <div class="auth-wordmark auth-wordmark-small">
                        <div class="flex items-center gap-3">
                            <div class="brand-emblem">
                                <span>W</span>
                            </div>
                            <p>WN<span>Fit</span></p>
                        </div>
                    </div>
                    <RouterLink
                        to="/entrar"
                        class="hidden text-sm font-medium text-[var(--wn-green)] transition hover:brightness-90 sm:inline-flex"
                    >
                        Já tenho conta
                    </RouterLink>
                </div>

                <div class="register-heading space-y-1">
                    <p class="text-sm font-medium text-[var(--wn-green)]">
                        Etapa {{ phase }} de 3<span v-if="step > 2 && step < 7"> · Pergunta {{ step - 2 }} de 4</span>
                    </p>
                    <h1 ref="stepTitle" tabindex="-1" class="auth-login-title">
                        {{ currentTitle }}
                    </h1>
                    <p class="text-sm leading-6 text-[var(--wn-muted)]">
                        {{ currentDescription }}
                    </p>
                </div>

                <ol aria-label="Etapas do cadastro" class="grid grid-cols-3 gap-3 text-xs text-[var(--wn-muted)]">
                    <li
                        v-for="(label, index) in phases"
                        :key="label"
                        :aria-current="phase === index + 1 ? 'step' : undefined"
                        class="border-t-2 pt-2"
                        :class="
                            phase >= index + 1
                                ? 'border-[var(--wn-green)] text-[var(--wn-ink)]'
                                : 'border-[var(--wn-line)]'
                        "
                    >
                        {{ index + 1 }}. {{ label }}<span v-if="index + 1 < phase" class="sr-only"> — concluída</span>
                    </li>
                </ol>
                <form
                    ref="formElement"
                    class="register-steps"
                    novalidate
                    :aria-busy="loading || checkingEmail"
                    @submit.prevent="advance"
                    @input="clearFieldError"
                    @change="clearFieldError"
                >
                    <fieldset :disabled="loading || checkingEmail" :aria-label="currentTitle" class="register-fields">
                        <div
                            ref="stepContent"
                            class="register-content grid gap-3"
                            role="region"
                            :aria-label="currentTitle"
                            tabindex="0"
                        >
                            <label v-if="step === 1" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]">Nome do responsável</span>
                                <div class="input-shell">
                                    <UserRound class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model.trim="form.name"
                                        name="name"
                                        minlength="2"
                                        :aria-invalid="Boolean(fieldErrors.name)"
                                        :aria-describedby="fieldErrors.name ? 'error-name' : undefined"
                                        type="text"
                                        placeholder="Seu nome"
                                        class="auth-input"
                                        autocomplete="name"
                                        maxlength="255"
                                        required
                                    />
                                </div>
                                <span v-if="fieldErrors.name" id="error-name" class="block text-sm text-rose-600">{{
                                    fieldErrors.name
                                }}</span></label
                            >

                            <label v-if="step === 1" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]">E-mail</span>
                                <div class="input-shell">
                                    <Mail class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model.trim="form.email"
                                        name="email"
                                        :aria-invalid="Boolean(fieldErrors.email)"
                                        :aria-describedby="fieldErrors.email ? 'error-email' : undefined"
                                        type="email"
                                        placeholder="E-mail"
                                        class="auth-input"
                                        autocomplete="email"
                                        maxlength="255"
                                        required
                                    />
                                </div>
                                <span v-if="fieldErrors.email" id="error-email" class="block text-sm text-rose-600">{{
                                    fieldErrors.email
                                }}</span></label
                            >

                            <div v-if="question" class="onboarding-question">
                                <fieldset
                                    class="onboarding-options"
                                    :aria-describedby="fieldErrors[question.key] ? 'error-' + question.key : undefined"
                                >
                                    <legend class="sr-only">{{ question.title }}</legend>
                                    <label
                                        v-for="option in question.options"
                                        :key="option.value"
                                        class="onboarding-option"
                                        :class="{ selected: isSelected(option.value) }"
                                    >
                                        <input
                                            v-if="question.key !== 'priorities'"
                                            v-model="profile[question.key]"
                                            type="radio"
                                            :name="question.key"
                                            :value="option.value"
                                            :aria-invalid="Boolean(fieldErrors[question.key])"
                                        />
                                        <input
                                            v-else
                                            v-model="profile.priorities"
                                            type="checkbox"
                                            name="priorities"
                                            :value="option.value"
                                            :aria-invalid="Boolean(fieldErrors.priorities)"
                                            :disabled="profile.priorities.length >= 3 && !isSelected(option.value)"
                                        />
                                        <span
                                            ><strong>{{ option.label }}</strong
                                            ><small v-if="option.detail">{{ option.detail }}</small></span
                                        >
                                    </label>
                                </fieldset>
                                <p v-if="question.key === 'priorities'" class="onboarding-help" aria-live="polite">
                                    {{ profile.priorities.length }} de 3 selecionadas<span
                                        v-if="profile.priorities.length === 3"
                                        >. Desmarque uma opção para trocar.</span
                                    >
                                </p>
                                <p
                                    v-if="fieldErrors[question.key]"
                                    :id="'error-' + question.key"
                                    class="text-sm text-rose-600"
                                >
                                    {{ fieldErrors[question.key] }}
                                </p>
                            </div>
                            <label v-if="step === 7" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]"
                                    >Nome profissional ou da organização</span
                                >
                                <div class="input-shell">
                                    <Building2 class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model.trim="form.company"
                                        name="company"
                                        minlength="2"
                                        :aria-invalid="Boolean(fieldErrors.company)"
                                        :aria-describedby="fieldErrors.company ? 'error-company' : undefined"
                                        type="text"
                                        placeholder="Como seus alunos conhecem você"
                                        class="auth-input"
                                        autocomplete="organization"
                                        maxlength="255"
                                        required
                                    />
                                </div>
                                <span
                                    v-if="fieldErrors.company"
                                    id="error-company"
                                    class="block text-sm text-rose-600"
                                    >{{ fieldErrors.company }}</span
                                ></label
                            >

                            <label v-if="step === 7" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]"
                                    >Telefone <small class="register-optional">(opcional)</small></span
                                >
                                <div class="input-shell">
                                    <Phone class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model="form.phone"
                                        name="phone"
                                        :aria-invalid="Boolean(fieldErrors.phone)"
                                        :aria-describedby="fieldErrors.phone ? 'phone-help error-phone' : 'phone-help'"
                                        type="tel"
                                        minlength="10"
                                        inputmode="numeric"
                                        pattern="[0-9]{10,15}"
                                        @input="sanitizePhone"
                                        placeholder="71999991234"
                                        class="auth-input"
                                        autocomplete="tel"
                                    />
                                </div>
                                <span id="phone-help" class="register-field-help"
                                    >Somente números, com DDD. De 10 a 15 dígitos.</span
                                >
                                <span v-if="fieldErrors.phone" id="error-phone" class="block text-sm text-rose-600">{{
                                    fieldErrors.phone
                                }}</span></label
                            >

                            <label v-if="step === 2" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]">Senha</span>
                                <div class="input-shell">
                                    <LockKeyhole class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model="form.password"
                                        name="password"
                                        :aria-invalid="Boolean(fieldErrors.password)"
                                        :aria-describedby="
                                            fieldErrors.password
                                                ? 'password-requirements error-password'
                                                : 'password-requirements'
                                        "
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="Senha segura"
                                        class="auth-input"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="text-[var(--wn-muted)] transition hover:text-[var(--wn-ink)]"
                                        :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                        :title="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <span
                                    v-if="fieldErrors.password"
                                    id="error-password"
                                    class="block text-sm text-rose-600"
                                    >{{ fieldErrors.password }}</span
                                ></label
                            >

                            <label v-if="step === 2" class="block space-y-1.5">
                                <span class="text-sm font-medium text-[var(--wn-ink)]">Confirmar senha</span>
                                <div class="input-shell">
                                    <ShieldCheck class="h-5 w-5 text-[var(--wn-muted)]" />
                                    <input
                                        v-model="form.password_confirmation"
                                        name="password_confirmation"
                                        :aria-invalid="Boolean(fieldErrors.password_confirmation)"
                                        :aria-describedby="
                                            fieldErrors.password_confirmation
                                                ? 'password-requirements error-password_confirmation'
                                                : 'password-requirements'
                                        "
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="Repita a senha"
                                        class="auth-input"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                    />
                                </div>
                                <span
                                    v-if="fieldErrors.password_confirmation"
                                    id="error-password_confirmation"
                                    class="block text-sm text-rose-600"
                                    >{{ fieldErrors.password_confirmation }}</span
                                ></label
                            >

                            <ul
                                v-if="step === 2"
                                id="password-requirements"
                                class="password-requirements"
                                aria-label="Requisitos da senha"
                            >
                                <li
                                    v-for="rule in passwordRules"
                                    :key="rule.label"
                                    :class="{ fulfilled: rule.fulfilled }"
                                >
                                    <Check v-if="rule.fulfilled" :size="16" aria-hidden="true" />
                                    <Circle v-else :size="16" aria-hidden="true" />
                                    <span
                                        >{{ rule.label
                                        }}<span class="sr-only">{{
                                            rule.fulfilled ? ': atendido' : ': pendente'
                                        }}</span></span
                                    >
                                </li>
                            </ul>
                            <dl
                                v-if="step === 8"
                                class="onboarding-review grid gap-4 text-sm sm:grid-cols-2"
                                aria-label="Dados do cadastro"
                            >
                                <div v-for="item in summary" :key="item.label" class="min-w-0">
                                    <dt class="text-[var(--wn-muted)]">{{ item.label }}</dt>
                                    <dd class="break-words font-medium text-[var(--wn-ink)]">{{ item.value }}</dd>
                                </div>
                            </dl>
                            <button v-if="step === 8" type="button" class="onboarding-edit" @click="goToStep(3)">
                                Revisar minhas respostas
                            </button>
                            <label
                                v-if="step === 8"
                                class="flex items-start gap-3 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] px-4 py-3 text-sm leading-6 text-[var(--wn-muted)]"
                            >
                                <input
                                    v-model="acceptedTerms"
                                    name="terms"
                                    :aria-invalid="Boolean(fieldErrors.terms)"
                                    :aria-describedby="fieldErrors.terms ? 'error-terms' : undefined"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-[var(--wn-line)] text-[var(--wn-green)] focus:ring-[var(--wn-green)]"
                                />
                                <span>Li e aceito os termos de uso para criar meu ambiente no WNFit.</span>
                            </label>

                            <span
                                v-if="step === 8 && fieldErrors.terms"
                                id="error-terms"
                                class="text-sm text-rose-600"
                                >{{ fieldErrors.terms }}</span
                            >
                            <ActionFeedback :message="error" type="error" />
                        </div>
                        <div class="register-actions">
                            <button
                                type="submit"
                                class="btn-primary w-full justify-center sm:w-auto sm:min-w-48"
                                :disabled="loading"
                            >
                                {{
                                    checkingEmail
                                        ? 'Verificando e-mail...'
                                        : loading
                                          ? 'Criando...'
                                          : step === 8
                                            ? 'Criar meu ambiente'
                                            : 'Continuar'
                                }}
                            </button>
                            <button
                                v-if="step > 1"
                                type="button"
                                class="btn-secondary w-full justify-center sm:w-auto"
                                @click="goToStep(step - 1)"
                            >
                                Voltar</button
                            ><RouterLink to="/entrar" class="register-login-link"> Voltar para login </RouterLink>
                        </div>
                    </fieldset>
                </form>
            </div>
        </main>
        <dialog
            ref="existingAccountDialog"
            class="existing-account-dialog"
            aria-labelledby="existing-account-title"
            aria-describedby="existing-account-description"
            @cancel.prevent="correctEmail"
            @keydown.tab="trapDialogFocus"
        >
            <h2 id="existing-account-title">Você já tem uma conta</h2>
            <p id="existing-account-description">
                Já existe uma conta com o e-mail <strong>{{ form.email }}</strong
                >. Faça login para acessar seu ambiente ou corrija o e-mail para continuar o cadastro.
            </p>
            <div class="existing-account-actions">
                <button type="button" class="btn-secondary" autofocus @click="correctEmail">Corrigir e-mail</button>
                <RouterLink to="/entrar" class="btn-primary">Ir para login</RouterLink>
            </div>
        </dialog>
    </div>
</template>

<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { computed, nextTick, reactive, ref } from 'vue';
import {
    Building2,
    Check,
    Circle,
    Eye,
    EyeOff,
    LockKeyhole,
    Mail,
    Phone,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';

import { questions, describeAnswer } from '../onboarding/profile';
import AuthShowcase from '../components/AuthShowcase.vue';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const loading = ref(false);
const checkingEmail = ref(false);
const existingAccountDialog = ref(null);
const trapDialogFocus = (event) => {
    const controls = existingAccountDialog.value.querySelectorAll('button, a[href]');
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
const correctEmail = async () => {
    existingAccountDialog.value?.close();
    await nextTick();
    formElement.value.querySelector('[name="email"]')?.focus();
};
const showExistingAccount = async () => {
    checkingEmail.value = false;
    loading.value = false;
    await nextTick();
    existingAccountDialog.value?.showModal();
};
const error = ref('');
const fieldErrors = ref({});
const step = ref(1);
const stepTitle = ref(null);
const formElement = ref(null);
const stepContent = ref(null);
const phases = ['Sua conta', 'Sua rotina', 'Seu ambiente'];
const phase = computed(() => (step.value <= 2 ? 1 : step.value >= 7 ? 3 : 2));
const question = computed(() => questions[step.value - 3] ?? null);
const currentTitle = computed(() =>
    step.value === 1
        ? 'Vamos começar por você'
        : step.value === 2
          ? 'Proteja sua conta'
          : step.value === 7
            ? 'Seu ambiente, do seu jeito'
            : step.value === 8
              ? 'Confira seus dados'
              : question.value.title,
);
const currentDescription = computed(() =>
    step.value === 1
        ? 'Informe o nome do responsável e o e-mail que você vai usar para acessar o WNFit.'
        : step.value === 2
          ? 'Crie uma senha com pelo menos 8 caracteres e confirme para continuar.'
          : step.value === 7
            ? 'Como seus alunos conhecem você? Informe seu nome profissional ou o nome do negócio.'
            : step.value === 8
              ? 'Está tudo certo? Confira as informações e aceite os termos para criar seu ambiente.'
              : question.value.description,
);
const profile = reactive({ student_range: '', service_mode: '', team: '', priorities: [] });
const fieldsByStep = {
    1: ['name', 'email'],
    2: ['password', 'password_confirmation'],
    3: ['student_range'],
    4: ['service_mode'],
    5: ['team'],
    6: ['priorities'],
    7: ['company', 'phone'],
    8: ['terms'],
};
const summary = computed(() => [
    { label: 'Responsável', value: form.name },
    { label: 'E-mail', value: form.email },
    { label: 'Nome profissional ou do negócio', value: form.company },
    { label: 'Telefone', value: form.phone || 'Não informado' },
    ...questions.map((q) => ({
        label:
            q.key === 'priorities'
                ? 'Suas prioridades'
                : { student_range: 'Alunos hoje', service_mode: 'Atendimento', team: 'Equipe' }[q.key],
        value:
            q.key === 'priorities'
                ? profile.priorities.map((v) => describeAnswer(q.key, v)).join(' · ')
                : describeAnswer(q.key, profile[q.key]),
    })),
]);
const goToStep = async (target) => {
    step.value = target;
    await nextTick();
    if (stepContent.value) stepContent.value.scrollTop = 0;
    stepTitle.value?.focus({ preventScroll: true });
};
const clearFieldError = (event) => {
    if (event.type === 'input' && ['checkbox', 'radio'].includes(event.target.type)) return;
    const field = event.target.name;
    if (field) delete fieldErrors.value[field];
    error.value = '';
};
const sanitizePhone = (event) => {
    const digits = event.target.value.replace(/[^0-9]/g, '').slice(0, 15);
    form.phone = digits;
    event.target.value = digits;
};
const validateStep = () => {
    const errors = {};
    for (const field of fieldsByStep[step.value]) {
        const input = formElement.value.elements.namedItem(field);
        if (input && typeof input.checkValidity === 'function' && !input.checkValidity())
            errors[field] = input.validationMessage;
        if (['name', 'company'].includes(field) && !form[field].trim()) errors[field] = 'Preencha este campo.';
    }
    if (question.value) {
        const key = question.value.key;
        if (key === 'priorities' ? profile.priorities.length < 1 || profile.priorities.length > 3 : !profile[key])
            errors[key] =
                key === 'priorities'
                    ? 'Escolha de 1 a 3 prioridades para começar.'
                    : 'Selecione uma opção para continuar.';
    }
    if (step.value === 7 && form.phone && !/^[0-9]{10,15}$/.test(form.phone))
        errors.phone = 'Informe de 10 a 15 números, incluindo o DDD.';
    if (step.value === 2 && Array.from(form.password).length < 8)
        errors.password = 'A senha deve ter pelo menos 8 caracteres.';
    if (step.value === 2 && form.password !== form.password_confirmation)
        errors.password_confirmation = 'A confirmação de senha não confere.';
    if (step.value === 8 && !acceptedTerms.value) errors.terms = 'Você precisa aceitar os termos para continuar.';
    for (const field of fieldsByStep[step.value]) delete fieldErrors.value[field];
    Object.assign(fieldErrors.value, errors);
    if (Object.keys(errors).length) {
        error.value = 'Confira os campos indicados para continuar.';
        nextTick(() => formElement.value.querySelector('[aria-invalid="true"]')?.focus());
        return false;
    }
    return true;
};
const advance = async () => {
    if (loading.value || checkingEmail.value || existingAccountDialog.value?.open) return;
    form.email = form.email.trim().toLowerCase();
    error.value = '';
    if (!validateStep()) return;
    if (step.value === 1) {
        checkingEmail.value = true;
        try {
            await auth.refreshCsrf();
            const { data } = await window.axios.post('/api/register/check-email', { email: form.email });
            if (!data.available) {
                fieldErrors.value.email = 'Já existe uma conta com este e-mail. Faça login para continuar.';
                await showExistingAccount();
                return;
            }
        } catch (exception) {
            error.value =
                exception.response?.status === 429
                    ? 'Muitas tentativas de verificação. Aguarde um minuto e tente novamente.'
                    : 'Não foi possível verificar seu e-mail. Confira sua conexão e tente novamente.';
            return;
        } finally {
            checkingEmail.value = false;
        }
    }
    if (step.value < 8) return goToStep(step.value + 1);
    return submit();
};
const isSelected = (value) =>
    question.value.key === 'priorities' ? profile.priorities.includes(value) : profile[question.value.key] === value;
const acceptedTerms = ref(false);
const showPassword = ref(false);

const form = reactive({
    name: '',
    email: '',
    company: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const passwordRules = computed(() => [
    { label: 'Pelo menos 8 caracteres', fulfilled: Array.from(form.password).length >= 8 },
    {
        label: 'As senhas são iguais',
        fulfilled: form.password.length > 0 && form.password === form.password_confirmation,
    },
]);

const submit = async () => {
    if (loading.value) return;
    loading.value = true;
    error.value = '';

    try {
        await auth.register({
            ...form,
            terms: acceptedTerms.value,
            onboarding_profile: { ...profile, priorities: [...profile.priorities] },
        });
        await router.replace('/painel');
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        fieldErrors.value = Object.fromEntries(
            Object.entries(errors ?? {}).map(([field, messages]) => [
                field.replace(/^onboarding_profile\./, '').replace(/\.\d+$/, ''),
                Array.isArray(messages) ? messages[0] : messages,
            ]),
        );
        const firstStep = [1, 2, 3, 4, 5, 6, 7, 8].find((target) =>
            fieldsByStep[target].some((field) => fieldErrors.value[field]),
        );
        error.value = errors
            ? 'Confira os campos indicados e tente novamente.'
            : 'Não foi possível criar a conta. Confira sua conexão e tente novamente.';
        if (firstStep) await goToStep(firstStep);
        if (firstStep === 1 && /j[aá] existe/i.test(fieldErrors.value.email ?? '')) await showExistingAccount();
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.auth-register-page {
    height: 100dvh;
    min-height: 0;
    overflow: hidden;
}
.auth-register-page .student-login-form {
    height: 100%;
    min-height: 0;
    margin: 0 auto;
    padding-block: 32px;
    justify-content: start;
}
.auth-register-page .auth-register-card {
    height: 100%;
    min-height: 0;
    grid-template-rows: 24px 164px auto minmax(0, 1fr);
    gap: 18px;
}
.register-heading {
    min-height: 0;
}
.register-steps {
    min-height: 0;
    margin: 0;
}
.register-fields {
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto;
    height: 100%;
    min-height: 0;
    min-width: 0;
    gap: 16px;
}
.register-content {
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    align-content: start;
    overscroll-behavior-y: contain;
    scrollbar-gutter: stable;
    padding: 4px 8px 8px 4px;
    margin: -4px -8px -8px -4px;
}
.register-content:focus-visible {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: 2px;
    border-radius: 4px;
}
.register-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    padding-top: 4px;
}
.auth-register-page :deep(.student-login-story) {
    min-height: 0;
    height: 100%;
    overflow: hidden;
}
@media (min-width: 1200px) {
    .auth-register-page .auth-register-card {
        grid-template-rows: 24px 120px auto minmax(0, 1fr);
    }
}
@media (max-width: 1023px) {
    .auth-register-page {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: 72px minmax(0, 1fr);
    }
    .auth-register-page :deep(.student-login-story) {
        padding: 16px 24px;
    }
    .auth-register-page :deep(.student-login-message),
    .auth-register-page :deep(.student-login-caption) {
        display: none;
    }
    .auth-register-page .student-login-form {
        grid-row: 2;
        flex: initial;
        padding: 24px;
    }
    .auth-register-page .auth-register-card {
        grid-template-rows: 176px auto minmax(0, 1fr);
    }
    .register-topbar {
        display: none;
    }
    .register-actions > .btn-primary {
        font-size: 14px;
        padding-inline: 12px;
        white-space: nowrap;
        flex: 1;
        width: auto;
        min-width: 0;
    }
    .register-actions > .btn-secondary {
        flex: 0 0 auto;
        width: auto;
    }
    .register-actions > .register-login-link {
        flex-basis: 100%;
    }
}
@media (min-width: 640px) and (max-width: 1023px) {
    .auth-register-page .auth-register-card {
        grid-template-rows: 120px auto minmax(0, 1fr);
    }
}
@media (max-height: 640px) {
    .auth-register-page .student-login-form {
        padding-block: 16px;
    }
    .auth-register-page .auth-register-card {
        gap: 12px;
        grid-template-rows: 112px auto minmax(0, 1fr);
    }
    .register-topbar {
        display: none;
    }
    .register-heading .auth-login-title {
        font-size: 24px;
    }
    .register-heading > p:last-child {
        font-size: 12px;
        line-height: 1.6;
    }
    .register-fields {
        gap: 10px;
    }
    .register-actions {
        gap: 8px;
    }
    .register-actions > .register-login-link {
        padding-block: 4px;
    }
}
@media (max-width: 1023px) and (max-height: 640px) {
    .auth-register-page {
        grid-template-rows: 56px minmax(0, 1fr);
    }
    .auth-register-page :deep(.student-login-story) {
        padding-block: 8px;
    }
}
.register-login-link {
    align-self: center;
    padding: 10px 4px;
    color: var(--wn-muted);
    font-size: 13px;
    text-align: center;
    text-underline-offset: 4px;
}
.register-login-link:hover {
    color: var(--wn-ink);
    text-decoration: underline;
}
.register-login-link:focus-visible {
    outline: 2px solid var(--wn-green);
    outline-offset: 4px;
    border-radius: 4px;
}
.auth-register-card .register-field-help {
    display: block;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.6;
    color: var(--wn-muted);
}
.register-optional {
    font-size: 12px;
    font-weight: 400;
    color: var(--wn-muted);
}

.onboarding-question {
    display: grid;
    gap: 12px;
}
.onboarding-options {
    display: grid;
    gap: 10px;
    min-width: 0;
}
.onboarding-option {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 14px 16px;
    border: 1px solid var(--wn-line);
    border-radius: 12px;
    cursor: pointer;
    background: white;
}
.onboarding-option:hover {
    border-color: var(--wn-primary-strong);
}
.onboarding-option.selected {
    border-color: var(--wn-primary-strong);
    background: var(--wn-primary-soft);
}
.onboarding-option:focus-within {
    outline: 2px solid var(--wn-primary-strong);
    outline-offset: 2px;
}
.onboarding-option input {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    accent-color: var(--wn-primary-strong);
}
.onboarding-option > span {
    display: grid;
    gap: 4px;
}
.onboarding-option strong {
    font-size: 14px;
    font-weight: 600;
}
.onboarding-option small {
    font-size: 12px;
    font-weight: 400;
    color: var(--wn-muted);
    line-height: 1.6;
}
.onboarding-option:has(input:disabled) {
    opacity: 0.55;
    cursor: default;
}
.onboarding-help {
    font-size: 12px;
    color: var(--wn-muted);
    line-height: 1.7;
}
.onboarding-edit {
    justify-self: start;
    font-size: 13px;
    color: var(--wn-primary-strong);
    text-decoration: underline;
    min-height: 44px;
}
.onboarding-review {
    padding: 16px;
    border: 1px solid var(--wn-line);
    border-radius: 12px;
    background: #f5f6f4;
}
@media (max-width: 480px) and (max-height: 640px) {
    .auth-register-page .auth-register-card {
        grid-template-rows: 156px auto minmax(0, 1fr);
    }
}
@media (max-width: 1023px) and (max-height: 480px) {
    .auth-register-page {
        grid-template-rows: 0 minmax(0, 1fr);
    }
    .auth-register-page :deep(.student-login-story) {
        display: none;
    }
    .auth-register-page .auth-register-card {
        grid-template-rows: 96px auto minmax(0, 1fr);
    }
    .auth-register-page .student-login-form {
        padding-block: 12px;
    }
    .register-actions > .register-login-link {
        flex: 0 1 auto;
    }
}

.existing-account-dialog {
    margin: auto;
    width: min(460px, calc(100vw - 32px));
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    padding: 28px;
    border: 1px solid var(--wn-line);
    border-radius: 18px;
    background: white;
    color: var(--wn-ink);
    box-shadow: 0 24px 80px #0003;
}
.existing-account-dialog::backdrop {
    background: #15171599;
}
.existing-account-dialog h2 {
    font-size: 24px;
    line-height: 1.3;
    font-weight: 700;
    letter-spacing: -0.025em;
}
.existing-account-dialog p {
    margin-top: 12px;
    font-size: 14px;
    line-height: 1.7;
    color: var(--wn-muted);
    overflow-wrap: anywhere;
}
.existing-account-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}
.existing-account-actions > * {
    flex: 1;
    justify-content: center;
    white-space: nowrap;
}
@media (max-width: 380px) {
    .existing-account-dialog {
        padding: 20px;
    }
    .existing-account-actions {
        flex-direction: column;
    }
}
.password-requirements {
    display: grid;
    gap: 8px;
    padding-top: 4px;
}
.password-requirements li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    line-height: 1.6;
    color: var(--wn-muted);
}
.password-requirements li.fulfilled {
    color: #365314;
}
.password-requirements svg {
    flex-shrink: 0;
}
</style>
