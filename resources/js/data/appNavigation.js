import {
    CalendarRange,
    Dumbbell,
    LayoutGrid,
    MessageSquareText,
    Puzzle,
    Settings,
    Users,
    Wallet,
} from 'lucide-vue-next';

export const appNavigation = [
    { label: 'Painel', to: '/painel', icon: LayoutGrid },
    { label: 'Alunos', to: '/alunos', icon: Users, module: 'students' },
    { label: 'Financeiro', to: '/financeiro', icon: Wallet, module: 'finance' },
    { label: 'Treinos', to: '/treinos', icon: Dumbbell, module: 'workouts' },
    { label: 'Agenda', to: '/agenda', icon: CalendarRange, module: 'schedule' },
    { label: 'Mensagens', to: '/mensagens', icon: MessageSquareText, module: 'messaging', additional: true },
    { label: 'Configurações', to: '/configuracoes', icon: Settings, activePaths: ['/configuracoes/usuarios', '/configuracoes/planos', '/configuracoes/unidades', '/configuracoes/horarios'] },
    { label: 'Módulos', to: '/modulos', icon: Puzzle, internal: true },
];
