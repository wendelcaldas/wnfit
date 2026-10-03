<template>
    <section class="admin-hero">
        <div>
            <p v-if="eyebrow" class="admin-overline">{{ eyebrow }}</p>
            <h2>{{ title }}</h2>
            <p v-if="description" class="admin-hero-description">{{ description }}</p>
        </div>
        <slot />
    </section>
</template>
<script setup>
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>
