import { defineStore } from "pinia";

// Temporary credentials live only in memory, until the teacher dismisses them.
export const useStudentCredentialsStore = defineStore("studentCredentials", {
    state: () => ({ studentId: null, credentials: null }),
    actions: {
        set(studentId, credentials) {
            this.studentId = studentId;
            this.credentials = credentials;
        },
        clear() {
            this.studentId = null;
            this.credentials = null;
        },
    },
});
