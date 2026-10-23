<template>
    <div class="student-login-page auth-login-ui auth-register-page">
        <AuthShowcase />

        <main class="student-login-form">
            <div class="auth-register-card">
                <div class="flex items-center justify-between gap-4">
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

                <div class="space-y-1">
                    <p class="text-sm font-medium text-[var(--wn-green)]">Etapa {{ step }} de 3</p>
                    <h1 ref="stepTitle" tabindex="-1" class="auth-login-title">
                        {{ steps[step - 1] }}
                    </h1>
                    <p class="text-sm leading-6 text-[var(--wn-muted)]">
                        {{ descriptions[step - 1] }}
                    </p>
                </div>

                <ol aria-label="Etapas do cadastro" class="grid grid-cols-3 gap-3 text-xs text-[var(--wn-muted)]">
                    <li
                        v-for="(label, index) in steps"
                        :key="label"
                        :aria-current="step === index + 1 ? 'step' : undefined"
                        class="border-t-2 pt-2"
                        :class="
                            step >= index + 1
                                ? 'border-[var(--wn-green)] text-[var(--wn-ink)]'
                                : 'border-[var(--wn-line)]'
                        "
                    >
                        {{ index + 1 }}. {{ label }}<span v-if="index + 1 < step" class="sr-only"> — concluída</span>
                    </li>
                </ol>
                <form
                    ref="formElement"
                    class="grid gap-3 md:grid-cols-2 register-steps"
                    novalidate
                    :aria-busy="loading"
                    @submit.prevent="advance"
                    @input="clearFieldError"
                    @change="clearFieldError"
                >
                    <fieldset :disabled="loading" :aria-label="steps[step - 1]" class="contents">
                        <label v-if="step === 1" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Nome completo</span>
                            <div class="input-shell">
                                <UserRound class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input
                                    v-model="form.name"
                                    name="name"
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
                                    v-model="form.email"
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

                        <label v-if="step === 2" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Empresa ou estúdio</span>
                            <div class="input-shell">
                                <Building2 class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input
                                    v-model="form.company"
                                    name="company"
                                    :aria-invalid="Boolean(fieldErrors.company)"
                                    :aria-describedby="fieldErrors.company ? 'error-company' : undefined"
                                    type="text"
                                    placeholder="Organização"
                                    class="auth-input"
                                    autocomplete="organization"
                                    maxlength="255"
                                    required
                                />
                            </div>
                            <span v-if="fieldErrors.company" id="error-company" class="block text-sm text-rose-600">{{
                                fieldErrors.company
                            }}</span></label
                        >

                        <label v-if="step === 2" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Tipo de negócio</span>
                            <select
                                v-model="form.company_type"
                                name="company_type"
                                :aria-invalid="Boolean(fieldErrors.company_type)"
                                :aria-describedby="fieldErrors.company_type ? 'error-company_type' : undefined"
                                class="form-control"
                                required
                            >
                                <option value="" disabled>Selecione</option>
                                <option value="academia">Academia</option>
                                <option value="studio">Studio</option>
                                <option value="personal">Personal trainer</option>
                            </select>
                            <span
                                v-if="fieldErrors.company_type"
                                id="error-company_type"
                                class="block text-sm text-rose-600"
                                >{{ fieldErrors.company_type }}</span
                            ></label
                        >

                        <label v-if="step === 2" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Telefone</span>
                            <div class="input-shell">
                                <Phone class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input
                                    v-model="form.phone"
                                    name="phone"
                                    :aria-invalid="Boolean(fieldErrors.phone)"
                                    :aria-describedby="fieldErrors.phone ? 'error-phone' : undefined"
                                    type="tel"
                                    maxlength="20"
                                    placeholder="Telefone"
                                    class="auth-input"
                                    autocomplete="tel"
                                />
                            </div>
                            <span v-if="fieldErrors.phone" id="error-phone" class="block text-sm text-rose-600">{{
                                fieldErrors.phone
                            }}</span></label
                        >

                        <label v-if="step === 1" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Senha (min. 8 caracteres)</span>
                            <div class="input-shell">
                                <LockKeyhole class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input
                                    v-model="form.password"
                                    name="password"
                                    :aria-invalid="Boolean(fieldErrors.password)"
                                    :aria-describedby="fieldErrors.password ? 'error-password' : undefined"
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
                            <span v-if="fieldErrors.password" id="error-password" class="block text-sm text-rose-600">{{
                                fieldErrors.password
                            }}</span></label
                        >

                        <label v-if="step === 1" class="block space-y-1.5">
                            <span class="text-sm font-medium text-[var(--wn-ink)]">Confirmar senha</span>
                            <div class="input-shell">
                                <ShieldCheck class="h-5 w-5 text-[var(--wn-muted)]" />
                                <input
                                    v-model="form.password_confirmation"
                                    name="password_confirmation"
                                    :aria-invalid="Boolean(fieldErrors.password_confirmation)"
                                    :aria-describedby="
                                        fieldErrors.password_confirmation ? 'error-password_confirmation' : undefined
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

                        <dl
                            v-if="step === 3"
                            class="md:col-span-2 grid gap-3 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] p-4 text-sm"
                        >
                            <div v-for="item in summary" :key="item.label" class="min-w-0">
                                <dt class="text-[var(--wn-muted)]">
                                    {{ item.label }}
                                </dt>
                                <dd class="break-words font-medium text-[var(--wn-ink)]">
                                    {{ item.value }}
                                </dd>
                            </div>
                        </dl>
                        <label
                            v-if="step === 3"
                            class="md:col-span-2 flex items-start gap-3 rounded-xl border border-[var(--wn-line)] bg-[var(--wn-surface-soft)] px-4 py-3 text-sm leading-6 text-[var(--wn-muted)]"
                        >
                            <input
                                v-model="acceptedTerms"
                                name="terms"
                                :aria-invalid="Boolean(fieldErrors.terms)"
                                :aria-describedby="fieldErrors.terms ? 'error-terms' : undefined"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-[var(--wn-line)] text-[var(--wn-green)] focus:ring-[var(--wn-green)]"
                            />
                            <span>Li e aceito os termos de uso para criar o ambiente da minha empresa no WNFit.</span>
                        </label>

                        <span
                            v-if="step === 3 && fieldErrors.terms"
                            id="error-terms"
                            class="md:col-span-2 text-sm text-rose-600"
                            >{{ fieldErrors.terms }}</span
                        >
                        <p
                            v-if="error"
                            role="alert"
                            class="md:col-span-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-600"
                        >
                            {{ error }}
                        </p>

                        <div class="md:col-span-2 flex flex-col gap-3 pt-1 sm:flex-row">
                            <button
                                type="submit"
                                class="btn-primary w-full justify-center sm:w-auto sm:min-w-48"
                                :disabled="loading"
                            >
                                {{ loading ? 'Criando...' : step === 3 ? 'Criar meu ambiente' : 'Continuar' }}
                            </button>
                            <button
                                v-if="step > 1"
                                type="button"
                                class="btn-secondary w-full justify-center sm:w-auto"
                                @click="goToStep(step - 1)"
                            >
                                Voltar</button
                            ><RouterLink to="/entrar" class="btn-secondary w-full justify-center sm:w-auto sm:min-w-48">
                                Voltar para login
                            </RouterLink>
                        </div>
                    </fieldset>
                </form>
            </div>
        </main>
    </div>
</template>

<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { computed, nextTick, reactive, ref } from 'vue';
import { Building2, Eye, EyeOff, LockKeyhole, Mail, Phone, ShieldCheck, UserRound } from 'lucide-vue-next';

import AuthShowcase from '../components/AuthShowcase.vue';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const loading = ref(false);
const error = ref('');
const fieldErrors = ref({});
const step = ref(1);
const stepTitle = ref(null);
const formElement = ref(null);
const steps = ['Sua conta', 'Seu negócio', 'Revisão e criação'];
const descriptions = [
    'Preencha os dados do responsável pela conta.',
    'Conte o essencial sobre seu ambiente.',
    'Confira os dados antes de criar seu ambiente.',
];
const fieldsByStep = {
    1: ['name', 'email', 'password', 'password_confirmation'],
    2: ['company', 'company_type', 'phone'],
    3: ['terms'],
};
const businessTypes = {
    academia: 'Academia',
    studio: 'Studio',
    personal: 'Personal trainer',
};
const summary = computed(() => [
    { label: 'Responsável', value: form.name },
    { label: 'E-mail', value: form.email },
    { label: 'Tipo de negócio', value: businessTypes[form.company_type] },
    { label: 'Nome do ambiente', value: form.company },
    ...(form.phone ? [{ label: 'Telefone', value: form.phone }] : []),
]);
const goToStep = async (target) => {
    step.value = target;
    await nextTick();
    stepTitle.value?.focus();
};
const clearFieldError = (event) => {
    const field = event.target.name;
    if (field) delete fieldErrors.value[field];
    if (!Object.keys(fieldErrors.value).length) error.value = '';
};
const validateStep = () => {
    const errors = {};
    for (const field of fieldsByStep[step.value]) {
        const input = formElement.value.elements.namedItem(field);
        if (input && !input.checkValidity()) errors[field] = input.validationMessage;
        if (['name', 'company'].includes(field) && !form[field].trim()) errors[field] = 'Preencha este campo.';
    }
    if (step.value === 1 && form.password !== form.password_confirmation)
        errors.password_confirmation = 'A confirmação de senha não confere.';
    if (step.value === 3 && !acceptedTerms.value) errors.terms = 'Você precisa aceitar os termos para continuar.';
    for (const field of fieldsByStep[step.value]) delete fieldErrors.value[field];
    Object.assign(fieldErrors.value, errors);
    if (Object.keys(errors).length) {
        error.value = 'Confira os campos indicados para continuar.';
        nextTick(() => formElement.value.querySelector('[aria-invalid="true"]')?.focus());
        return false;
    }
    return true;
};
const advance = () => {
    if (loading.value) return;
    error.value = '';
    if (!validateStep()) return;
    if (step.value < 3) return goToStep(step.value + 1);
    return submit();
};
const acceptedTerms = ref(false);
const showPassword = ref(false);

const form = reactive({
    name: '',
    email: '',
    company: '',
    company_type: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const submit = async () => {
    if (loading.value) return;
    loading.value = true;
    error.value = '';

    try {
        await auth.register({
            ...form,
            terms: acceptedTerms.value,
        });
        await router.replace('/painel');
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        fieldErrors.value = Object.fromEntries(
            Object.entries(errors ?? {}).map(([field, messages]) => [
                field,
                Array.isArray(messages) ? messages[0] : messages,
            ]),
        );
        const firstStep = [1, 2, 3].find((target) => fieldsByStep[target].some((field) => fieldErrors.value[field]));
        error.value = errors
            ? 'Confira os campos indicados e tente novamente.'
            : 'Não foi possível criar a conta. Confira sua conexão e tente novamente.';
        if (firstStep) await goToStep(firstStep);
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.register-steps {
    min-height: 390px;
    align-content: start;
}
</style>
