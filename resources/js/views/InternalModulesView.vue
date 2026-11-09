<template>
    <AppShell eyebrow="Interno WNFit" title="Módulos" description="Gerencie os módulos adicionais de cada ambiente.">
        <section class="panel-card mb-4" aria-labelledby="basic-modules-title">
            <h2 id="basic-modules-title" class="font-semibold">Módulos básicos</h2>
            <p class="mt-1 text-sm text-[var(--wn-muted)]">Incluídos em todos os ambientes.</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                <li v-for="item in basicModules" :key="item.module" class="rounded-lg bg-[var(--wn-surface-soft)] px-3 py-2 text-sm">
                    {{ item.label }}
                </li>
            </ul>
        </section>

        <section class="panel-card" aria-labelledby="additional-modules-title">
            <h2 id="additional-modules-title" class="font-semibold">Módulos adicionais</h2>
            <p class="mb-4 mt-1 text-sm text-[var(--wn-muted)]">Libere os adicionais contratados por cada ambiente.</p>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
                <label class="grid w-full max-w-sm gap-1 text-sm font-medium">
                    Buscar ambiente
                    <input v-model="query" type="search" class="form-control" placeholder="Nome ou identificador" maxlength="150" />
                </label>
                <button class="btn-primary" :disabled="loading || saving">Buscar</button>
            </form>

            <p v-if="loading" class="mt-5 text-sm text-[var(--wn-muted)]" role="status">Carregando ambientes…</p>
            <p v-else-if="loadError" class="mt-5 text-sm text-rose-700" role="alert">{{ loadError }}</p>
            <p v-else-if="!organizations.length" class="mt-5 text-sm text-[var(--wn-muted)]">Nenhum ambiente encontrado.</p>
            <div v-else class="mt-5 divide-y divide-[var(--wn-line)]">
                <article v-for="organization in organizations" :key="organization.id" class="grid gap-3 py-4 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] md:items-center">
                    <div class="min-w-0 break-words">
                        <h2 class="font-semibold">{{ organization.name }}</h2>
                        <p class="text-sm text-[var(--wn-muted)]">{{ organization.slug }}<span v-if="!organization.active"> · Inativo</span></p>
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <label v-for="item in additionalModules" :key="item.module" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg bg-[var(--wn-surface-soft)] px-3 text-sm">
                            <input
                                type="checkbox"
                                role="switch"
                                class="size-4 shrink-0 accent-[var(--wn-primary-strong)]"
                                :checked="organization.modules[item.module]"
                                :disabled="saving"
                                :aria-label="item.label + ' em ' + organization.name"
                                @change="changeModule(organization, item, $event)"
                            />
                            {{ item.label }}
                        </label>
                    </div>
                </article>
            </div>

            <nav v-if="!loading && !loadError && lastPage > 1" class="mt-4 flex flex-wrap items-center gap-3" aria-label="Páginas de ambientes">
                <button class="btn-secondary" type="button" :disabled="page === 1 || saving" @click="load(page - 1)">Anterior</button>
                <span class="text-sm text-[var(--wn-muted)]">Página {{ page }} de {{ lastPage }}</span>
                <button class="btn-secondary" type="button" :disabled="page === lastPage || saving" @click="load(page + 1)">Próxima</button>
            </nav>
        </section>
    </AppShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppShell from '../components/AppShell.vue';
import { notify } from '../composables/useNotifications';
import { appNavigation } from '../data/appNavigation';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const basicModules = appNavigation.filter((item) => item.module && !item.additional);
const additionalModules = appNavigation.filter((item) => item.additional);
const organizations = ref([]);
const query = ref('');
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const loadError = ref('');
const saving = ref(false);
let appliedQuery = '';

function search() {
    appliedQuery = query.value.trim();
    return load(1);
}

async function load(targetPage = 1) {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await window.axios.get('/api/internal/modules', { params: { q: appliedQuery, page: targetPage } });
        organizations.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
    } catch {
        loadError.value = 'Não foi possível carregar os ambientes. Tente buscar novamente.';
    } finally {
        loading.value = false;
    }
}

async function changeModule(organization, item, event) {
    if (saving.value) return;
    const input = event.target;
    const enabled = input.checked;
    saving.value = true;
    try {
        const { data } = await window.axios.patch('/api/internal/modules/' + organization.id, { module: item.module, enabled });
        organization.modules = data.modules;
        if (auth.organization?.id === organization.id) auth.organization.modules = data.modules;
        notify(enabled ? 'Módulo habilitado.' : 'Módulo desabilitado.');
    } catch {
        input.checked = organization.modules[item.module];
        notify('Não foi possível atualizar o módulo.', { type: 'error' });
    } finally {
        saving.value = false;
    }
}

onMounted(() => load());
</script>
