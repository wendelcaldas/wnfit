<template>
    <StudentShell>
        <div class="mb-7">
            <p class="student-overline">VAMOS NOS ENCONTRAR</p>
            <h1 class="mt-2 font-display text-3xl font-bold">Evento</h1>
            <p class="mt-2 text-sm text-[var(--wn-muted)]">
                Um espaço para viver novas experiências com o WNFit.
            </p>
        </div>
        <section class="student-card !p-7">
            <div class="student-icon"><CalendarDays :size="24" /></div>
            <h2 class="mt-5 text-xl font-bold">Novidades em breve</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--wn-muted)]">
                Estamos preparando os detalhes do próximo evento.
                Quando estiver tudo pronto, você acompanha por aqui.
            </p>
        </section>
    </StudentShell>
</template>
<script setup>
import { CalendarDays } from "lucide-vue-next";
import StudentShell from "../components/StudentShell.vue";
</script>
