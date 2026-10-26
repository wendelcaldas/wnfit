<template>
    <div ref="root" class="header-account relative" @keydown.esc.stop.prevent="close(true)">
        <button ref="trigger" type="button" class="account-avatar" aria-label="Menu do usuário" :aria-expanded="open" aria-controls="header-account-options" @click="open = !open" @keydown.down.prevent="openAndFocus">
            <img v-if="avatarUrl && !imageFailed" :src="avatarUrl" alt="" class="h-full w-full rounded-full object-cover" @error="imageFailed = true">
            <span v-else aria-hidden="true">{{ auth.initials }}</span>
        </button>
        <div v-if="open" id="header-account-options" class="header-account-menu">
            <RouterLink ref="settings" to="/configuracoes/usuarios" class="header-account-item" @click="close()"><Settings class="h-4 w-4" aria-hidden="true" />Configurações</RouterLink>
            <button type="button" class="header-account-item w-full text-rose-600" @click="logout"><LogOut class="h-4 w-4" aria-hidden="true" />Sair</button>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { LogOut, Settings } from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const root = ref(null);
const trigger = ref(null);
const settings = ref(null);
const open = ref(false);
const imageFailed = ref(false);
const avatarUrl = computed(() => auth.user?.avatar_url || auth.user?.photo_url || auth.user?.profile_photo_url || null);
watch(avatarUrl, () => { imageFailed.value = false; });
watch(() => route.fullPath, () => close());
function close(restoreFocus = false) { open.value = false; if (restoreFocus) trigger.value?.focus(); }
async function openAndFocus() { open.value = true; await nextTick(); settings.value?.$el.focus(); }
function outside(event) { if (!root.value?.contains(event.target)) close(); }
async function logout() { close(); await auth.logout(); router.push('/entrar'); }
onMounted(() => { document.addEventListener('pointerdown', outside); document.addEventListener('focusin', outside); });
onBeforeUnmount(() => { document.removeEventListener('pointerdown', outside); document.removeEventListener('focusin', outside); });
</script>

<style scoped>
.account-avatar { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; overflow: hidden; border-radius: 50%; background: var(--wn-neutral-soft); color: var(--wn-ink); font-size: .875rem; font-weight: 600; cursor: pointer; }
.account-avatar:hover, .account-avatar[aria-expanded='true'] { box-shadow: 0 0 0 2px var(--wn-line); }
.account-avatar:focus-visible { outline: 2px solid var(--wn-primary-strong); outline-offset: 3px; }
.header-account-menu { max-width: calc(100vw - 2rem); }
</style>
