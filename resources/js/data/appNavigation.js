import {
    BarChart3,
    CalendarRange,
    Dumbbell,
    LayoutGrid,
    MessageSquareText,
    Settings,
    Users,
} from 'lucide-vue-next';

export const appNavigation = [
    { label: 'Painel', to: '/painel', icon: LayoutGrid },
    { label: 'Alunos', to: '/alunos', icon: Users },
    { label: 'Treinos', to: '/treinos', icon: Dumbbell },
    { label: 'Agenda', to: '#', icon: CalendarRange },
    { label: 'Relatorios', to: '#', icon: BarChart3 },
    { label: 'Comunicacoes', to: '#', icon: MessageSquareText },
    { label: 'Configuracoes', to: '/configuracoes/usuarios', icon: Settings },
];
