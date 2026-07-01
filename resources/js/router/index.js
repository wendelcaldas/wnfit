import { createRouter, createWebHistory } from 'vue-router';

import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import StudentDetailView from '../views/StudentDetailView.vue';
import StudentFormView from '../views/StudentFormView.vue';
import StudentsView from '../views/StudentsView.vue';
import UsersSettingsView from '../views/UsersSettingsView.vue';
import { useAuthStore } from '../stores/auth';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: '/entrar',
        },
        {
            path: '/entrar',
            name: 'entrar',
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: '/cadastro',
            name: 'cadastro',
            component: RegisterView,
            meta: { guest: true },
        },
        {
            path: '/painel',
            name: 'painel',
            component: DashboardView,
            meta: { requiresAuth: true },
        },
        {
            path: '/alunos',
            name: 'alunos',
            component: StudentsView,
            meta: { requiresAuth: true },
        },
        {
            path: '/alunos/novo',
            name: 'alunos.novo',
            component: StudentFormView,
            meta: { requiresAuth: true },
        },
        {
            path: '/alunos/:id',
            name: 'alunos.detalhe',
            component: StudentDetailView,
            meta: { requiresAuth: true },
        },
        {
            path: '/configuracoes/usuarios',
            name: 'configuracoes.usuarios',
            component: UsersSettingsView,
            meta: { requiresAuth: true },
        },
        {
            path: '/login',
            redirect: '/entrar',
        },
        {
            path: '/register',
            redirect: '/cadastro',
        },
        {
            path: '/dashboard',
            redirect: '/painel',
        },
        {
            path: '/students',
            redirect: '/alunos',
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.loaded) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return '/entrar';
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return '/painel';
    }
});
