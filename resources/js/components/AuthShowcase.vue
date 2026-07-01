<template>
    <section class="auth-showcase">
        <img :src="showcaseImage" alt="" class="auth-showcase-image" />

        <div class="auth-showcase-content">
            <div class="auth-showcase-wordmark">
                <div class="brand-emblem brand-emblem-large">
                    <span>W</span>
                </div>
                <p>WN<span>Fit</span></p>
            </div>

            <h1>
                Gestao inteligente para academias e studios que levam
                <span>performance</span> a serio.
            </h1>

            <div class="auth-feature-list">
                <div v-for="item in items" :key="item.title" class="auth-feature-line">
                    <CheckCircle2 class="h-5 w-5" />
                    <span>{{ item.title }}</span>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { CheckCircle2 } from 'lucide-vue-next';

const showcaseImage = '/images/auth-gym.png';

const items = [
    {
        title: 'Mais organizacao',
    },
    {
        title: 'Mais resultados',
    },
    {
        title: 'Mais tempo para o que importa',
    },
];
</script>
