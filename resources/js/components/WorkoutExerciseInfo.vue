<template>
    <details
        v-if="
            exercise.instructions ||
            exercise.notes ||
            exercise.safetyNotes ||
            mediaUrl ||
            imageUrl
        "
        class="mt-3 text-sm"
        @toggle="expanded = $event.target.open"
    >
        <summary
            class="cursor-pointer font-semibold text-[var(--wn-primary-strong)]"
        >
            Como fazer e orientações
        </summary>
        <div
            class="mt-3 space-y-3 rounded-xl bg-[var(--wn-surface-soft)] p-4 leading-6"
        >
            <figure
                v-if="expanded && imageUrl && !imageFailed"
                class="exercise-illustration"
            >
                <button
                    type="button"
                    class="exercise-image-button"
                    :aria-label="`Ampliar demonstração de ${exercise.name}`"
                    @click="illustrationDialog.showModal()"
                >
                    <img
                        :src="imageUrl"
                        :alt="
                            illustration?.alt ||
                            `Demonstração de ${exercise.name}`
                        "
                        :width="illustration?.width || 1200"
                        :height="illustration?.height || 800"
                        loading="lazy"
                        decoding="async"
                        @error="imageFailed = true"
                    />
                    <span>Ampliar demonstração</span>
                </button>
                <dialog
                    ref="illustrationDialog"
                    class="exercise-image-dialog"
                    :aria-labelledby="illustrationTitleId"
                >
                    <header>
                        <strong :id="illustrationTitleId">{{
                            exercise.name
                        }}</strong
                        ><button
                            type="button"
                            aria-label="Fechar demonstração"
                            @click="illustrationDialog.close()"
                        >
                            <X :size="22" />
                        </button>
                    </header>
                    <img
                        :src="imageUrl"
                        :alt="
                            illustration?.alt ||
                            `Demonstração de ${exercise.name}`
                        "
                        :width="illustration?.width || 1200"
                        :height="illustration?.height || 800"
                    />
                </dialog>
            </figure>
            <p
                v-else-if="expanded && imageFailed"
                class="text-xs text-[var(--wn-muted)]"
            >
                A imagem não carregou. As orientações abaixo continuam
                disponíveis.
            </p>
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
import { computed, ref, useId, watch } from "vue";
import { Play, X } from "lucide-vue-next";
import library from "../data/exerciseIllustrations.json";
const props = defineProps({ exercise: { type: Object, required: true } });
const expanded = ref(false);
const imageFailed = ref(false);
const illustrationDialog = ref(null);
const illustrationTitleId = useId();
const illustration = computed(() => {
    if (props.exercise.custom) return null;
    const name = (props.exercise.name || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase();
    return library[name] || null;
});
const imageUrl = computed(() => {
    const value = props.exercise.imageUrl || illustration.value?.url;
    if (!value) return null;
    try {
        const url = new URL(value, window.location.origin);
        return ["https:", "http:"].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
});
watch(imageUrl, () => {
    imageFailed.value = false;
});
const mediaUrl = computed(() => {
    try {
        const url = new URL(props.exercise.videoUrl);
        return ["https:", "http:"].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
});
</script>
<style scoped>
.exercise-illustration {
    overflow: hidden;
    margin: 0;
    border: 1px solid #e3e7df;
    border-radius: 12px;
    background: #fff;
}
.exercise-illustration img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
}
.exercise-image-button {
    display: block;
    width: 100%;
    text-align: center;
    cursor: zoom-in;
}
.exercise-image-button > span {
    display: block;
    padding: 8px;
    font-size: 12px;
    color: #365314;
    background: #eef8de;
}
.exercise-image-dialog {
    margin: auto;
    width: min(calc(100% - 24px), 1000px);
    max-height: 95dvh;
    padding: 12px;
    border: 1px solid #e3e7df;
    border-radius: 16px;
    background: #fff;
    color: #151715;
}
.exercise-image-dialog::backdrop {
    background: rgb(21 23 21 / 0.7);
}
.exercise-image-dialog header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.exercise-image-dialog header button {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #eef8de;
}
.exercise-image-dialog img {
    max-height: calc(95dvh - 84px);
    object-fit: contain;
}
.exercise-image-button:focus-visible,
.exercise-image-dialog button:focus-visible {
    outline: 2px solid #365314;
    outline-offset: 2px;
}
</style>
