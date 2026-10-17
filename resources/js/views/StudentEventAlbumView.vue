<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import StudentShell from '../components/StudentShell.vue';
import EventAlbum from '../components/EventAlbum.vue';
const route = useRoute(), detail = ref(null), loading = ref(true), error = ref('');
let version = 0;
async function load() {
    const current = ++version;
    loading.value = true; detail.value = null; error.value = '';
    try {
        const { data } = await window.axios.get('/api/student/events/' + route.params.id);
        if (current !== version) return;
        if (!data.event.modules.album.enabled) { error.value = 'O álbum deste evento não está disponível.'; return; }
        if (!data.event.modulesAvailable) { error.value = 'O álbum estará disponível no dia do evento.'; return; }
        detail.value = data;
    } catch (e) { if (current === version) error.value = e.response?.data?.message || 'Não foi possível carregar o álbum.'; }
    finally { if (current === version) loading.value = false; }
}
watch(() => route.params.id, load, { immediate: true });
onUnmounted(() => { version++; });
</script>
<template>
    <StudentShell>
        <RouterLink :to="'/aluno/evento/' + route.params.id" class="album-back">← Voltar ao evento</RouterLink>
        <p class="student-overline">MEMÓRIAS DO ENCONTRO</p>
        <h1 class="album-title">Nosso álbum</h1>
        <p v-if="detail" class="album-subtitle">{{ detail.event.name }}</p>
        <p v-if="loading" role="status">Carregando o álbum…</p>
        <p v-if="error" role="alert" class="student-error">{{ error }} <button @click="load">Tentar novamente</button></p>
        <EventAlbum v-if="detail" :key="detail.event.id" :event-id="detail.event.id" :instructions="detail.event.modules.album.instructions" />
    </StudentShell>
</template>
<style scoped>.album-back{display:inline-flex;align-items:center;min-height:44px;margin-bottom:16px;color:var(--wn-primary-strong)}.album-title{font-size:32px;font-weight:800;margin-top:8px}.album-subtitle{margin:8px 0 24px;color:var(--wn-muted);overflow-wrap:anywhere}</style>
