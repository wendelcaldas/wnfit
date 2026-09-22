import {
    BarChart3,
    CalendarRange,
    Dumbbell,
    LayoutGrid,
    MessageSquareText,
    Settings,
    Users,
    Wallet,
} from 'lucide-vue-next';

export const appNavigation = [
    { label: 'Painel', to: '/painel', icon: LayoutGrid },
    { label: 'Alunos', to: '/alunos', icon: Users },
    { label: 'Financeiro', to: '/financeiro', icon: Wallet },
    { label: 'Treinos', to: '/treinos', icon: Dumbbell },
    { label: 'Agenda', to: '/agenda', icon: CalendarRange },
    { label: 'Eventos', to: '/eventos', icon: CalendarRange },
    { label: 'Comunicacoes', to: '/configuracoes/mensagens', icon: MessageSquareText },
    { label: 'Configuracoes', to: '/configuracoes/usuarios', icon: Settings },
];
