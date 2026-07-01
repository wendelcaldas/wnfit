import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        organization: null,
        loaded: false,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        firstName: (state) => state.user?.name?.split(' ')[0] ?? 'Admin',
        initials: (state) => state.user?.name
            ?.split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase() ?? 'AD',
    },
    actions: {
        async fetchUser() {
            const { data } = await window.axios.get('/api/me');
            this.updateCsrf(data.csrf);
            this.user = data.user;
            this.organization = data.organization;
            this.loaded = true;

            return data;
        },
        async login(payload) {
            await this.refreshCsrf();

            const { data } = await window.axios.post('/api/login', payload);
            this.updateCsrf(data.csrf);
            this.user = data.user;
            this.organization = data.organization;
            this.loaded = true;

            return data;
        },
        async register(payload) {
            await this.refreshCsrf();

            const { data } = await window.axios.post('/api/register', payload);
            this.updateCsrf(data.csrf);
            this.user = data.user;
            this.organization = data.organization;
            this.loaded = true;

            return data;
        },
        async logout() {
            const { data } = await window.axios.post('/api/logout');
            this.updateCsrf(data.csrf);
            this.user = null;
            this.organization = null;
            this.loaded = true;
        },
        async refreshCsrf() {
            const { data } = await window.axios.get('/api/me');
            this.updateCsrf(data.csrf);
        },
        updateCsrf(token) {
            if (token) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
        },
    },
});
