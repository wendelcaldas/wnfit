<template>
    <section class="student-card !p-7" role="status">
        <div class="student-icon"><Dumbbell :size="24" /></div>
        <h2 class="mt-5 text-xl font-bold">{{ title }}</h2>
        <p v-if="access?.status === 'expired' && access.endsOn" class="mt-2 text-sm text-[var(--wn-muted)]">
            Vigência encerrada em {{ date(access.endsOn) }}.
        </p>
        <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
            {{ access?.message || 'Fale com seu professor para receber ou renovar seu programa de treino.' }}
        </p>
    </section>
</template>
<script setup>
import { computed } from "vue";
import { Dumbbell } from "lucide-vue-next";
const props = defineProps({ access: { type: Object, default: null } });
const title = computed(() => ({
    expired: "Sua ficha venceu",
    closed: "Esta ficha não está mais disponível",
    scheduled: "Seu treino ainda não começou",
    unavailable: "Sua ficha precisa de revisão",
}[props.access?.status] || "Aguardando uma ficha vigente"));
const date = (value) => new Date(`${value}T12:00:00`).toLocaleDateString("pt-BR");
</script>
