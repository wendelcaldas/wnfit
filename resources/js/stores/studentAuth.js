import { defineStore } from "pinia";

export const useStudentAuthStore = defineStore("studentAuth", {
    state: () => ({ student: null, loaded: false }),
    actions: {
        apply(data) {
            if (data.csrf)
                window.axios.defaults.headers.common["X-CSRF-TOKEN"] =
                    data.csrf;
            this.student = data.student;
            this.loaded = true;
        },
        async fetch() {
            const { data } = await window.axios.get("/api/student/me");
            this.apply(data);
        },
        async login(payload) {
            await this.fetch();
            const { data } = await window.axios.post(
                "/api/student/login",
                payload,
            );
            this.apply(data);
        },
        async password(payload) {
            const { data } = await window.axios.put(
                "/api/student/password",
                payload,
            );
            this.apply(data);
        },
        async logout() {
            const { data } = await window.axios.post("/api/student/logout");
            this.apply(data);
        },
    },
});
