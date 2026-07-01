<template>
    <div class="flex items-center gap-3">
        <div class="brand-emblem">
            <span>W</span>
        </div>
        <div>
            <p :class="titleClass">WN<span class="text-[var(--wn-primary-strong)]">Fit</span></p>
            <p :class="subtitleClass">{{ subtitle }}</p>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    dark: {
        type: Boolean,
        default: false,
    },
    subtitle: {
        type: String,
        default: 'Gestao que move resultados.',
    },
});

const titleClass = props.dark
    ? 'font-display text-[2rem] font-bold leading-none text-white'
    : 'font-display text-[1.7rem] font-bold leading-none text-[var(--wn-ink)]';

const subtitleClass = props.dark
    ? 'mt-1 text-[0.68rem] font-medium uppercase text-white/60'
    : 'mt-1 text-[0.68rem] font-medium uppercase text-[var(--wn-muted)]';
</script>
