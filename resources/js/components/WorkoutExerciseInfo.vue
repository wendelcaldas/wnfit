<template>
    <details
        v-if="
            exercise.instructions ||
            exercise.notes ||
            exercise.safetyNotes ||
            mediaUrl
        "
        class="mt-3 text-sm"
    >
        <summary
            class="cursor-pointer font-semibold text-[var(--wn-primary-strong)]"
        >
            Como fazer e orientações
        </summary>
        <div
            class="mt-3 space-y-3 rounded-xl bg-[var(--wn-surface-soft)] p-4 leading-6"
        >
            <p v-if="exercise.instructions" class="whitespace-pre-line">
                {{ exercise.instructions }}
            </p>
            <p v-if="exercise.notes">
                <strong>Seu professor:</strong> {{ exercise.notes }}
            </p>
            <p v-if="exercise.safetyNotes">
                <strong>Cuidados:</strong> {{ exercise.safetyNotes }}
            </p>
            <a
                v-if="mediaUrl"
                :href="mediaUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 font-semibold text-[var(--wn-primary-strong)]"
                ><Play :size="16" />Ver demonstração</a
            >
        </div>
    </details>
</template>
<script setup>
import { computed } from "vue";
import { Play } from "lucide-vue-next";
const props = defineProps({ exercise: { type: Object, required: true } });
const mediaUrl = computed(() => {
    try {
        const url = new URL(props.exercise.videoUrl);
        return ["https:", "http:"].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
});
</script>
