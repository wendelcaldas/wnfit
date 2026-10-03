<template>
    <section class="student-login-story">
        <div class="flex items-center gap-3"><span class="brand-emblem">W</span><strong class="font-display text-2xl">WNFit</strong></div>
        <div class="student-login-message">
            <span class="student-overline">SEU ESPAÇO DE GESTÃO</span>
            <h2 class="mt-5 font-display font-bold leading-tight">Seu estúdio. Uma nova <span class="student-brand-accent">experiência.</span></h2>
            <p class="mt-6 max-w-sm text-base leading-7 text-white/70">Organize seus alunos.<br />Acompanhe cada evolução.</p>
        </div>
        <p class="student-login-caption flex items-center gap-3 text-sm text-white/70"><Dumbbell :size="20" /> Cada aluno. Um novo resultado.</p>
    </section>
</template>

<script setup>
import { Dumbbell } from 'lucide-vue-next';
</script>
