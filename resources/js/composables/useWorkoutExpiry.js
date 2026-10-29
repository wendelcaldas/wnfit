import { onMounted, onUnmounted, watch } from "vue";

// The server owns the deadline. Remove a stale prescription even if the tab stays open.
export function useWorkoutExpiry({ expiresAt, onExpire, refresh }) {
    let timer;
    let expiredDeadline;
    let lastRefresh = 0;

    function check() {
        clearTimeout(timer);
        const deadline = expiresAt();
        const timestamp = Date.parse(deadline);
        if (!deadline || !Number.isFinite(timestamp)) return;
        const remaining = timestamp - Date.now();
        if (remaining <= 0) {
            if (expiredDeadline !== deadline) {
                expiredDeadline = deadline;
                onExpire();
            }
            return;
        }
        expiredDeadline = null;
        timer = setTimeout(check, Math.min(remaining, 60000));
    }

    function foreground() {
        if (document.visibilityState === "hidden") return;
        check();
        if (Date.now() - lastRefresh >= 1000) {
            lastRefresh = Date.now();
            refresh();
        }
    }

    watch(expiresAt, check, { immediate: true });
    onMounted(() => {
        window.addEventListener("focus", foreground);
        document.addEventListener("visibilitychange", foreground);
    });
    onUnmounted(() => {
        clearTimeout(timer);
        window.removeEventListener("focus", foreground);
        document.removeEventListener("visibilitychange", foreground);
    });
}

export const expiredWorkoutMessage =
    "A validade da sua ficha terminou. Fale com seu professor para renovar o treino.";
