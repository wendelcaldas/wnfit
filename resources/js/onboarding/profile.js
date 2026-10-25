export const questions = [
    {
        key: 'student_range',
        title: 'Quantos alunos você acompanha hoje?',
        description: 'Considere os alunos que você ou sua equipe atendem. Uma estimativa já ajuda.',
        options: [
            { value: 'starting', label: 'Estou começando', detail: 'Ainda estou formando minha carteira de alunos.' },
            { value: '1_20', label: '1 a 20 alunos' },
            { value: '21_50', label: '21 a 50 alunos' },
            { value: '51_100', label: '51 a 100 alunos' },
            { value: '100_plus', label: 'Mais de 100 alunos' },
        ],
    },
    {
        key: 'service_mode',
        title: 'Como você atende seus alunos?',
        description: 'Pense nos atendimentos que fazem parte da sua rotina hoje.',
        options: [
            {
                value: 'in_person',
                label: 'Presencialmente',
                detail: 'Encontro meus alunos para os atendimentos e aulas.',
            },
            { value: 'online', label: 'Online', detail: 'Oriento e acompanho meus alunos a distância.' },
            { value: 'hybrid', label: 'Das duas formas', detail: 'Combino atendimentos presenciais e online.' },
        ],
    },
    {
        key: 'team',
        title: 'Quem cuida da operação com você?',
        description: 'Isso nos ajuda a indicar os próximos passos para organizar seu trabalho.',
        options: [
            { value: 'solo', label: 'Só eu', detail: 'Cuido dos alunos e da gestão por conta própria.' },
            { value: 'team', label: 'Eu e uma equipe', detail: 'Divido o trabalho com outros profissionais.' },
        ],
    },
    {
        key: 'priorities',
        title: 'O que você quer organizar primeiro?',
        description:
            'Escolha até 3 prioridades. A primeira escolhida orienta seu ponto de partida, sem limitar os outros recursos.',
        options: [
            { value: 'workouts', label: 'Treinos', detail: 'Montar e organizar a prescrição dos alunos.' },
            { value: 'schedule', label: 'Agenda e aulas', detail: 'Planejar horários, aulas e participantes.' },
            { value: 'finance', label: 'Mensalidades e cobranças', detail: 'Acompanhar vencimentos e recebimentos.' },
            { value: 'progress', label: 'Evolução dos alunos', detail: 'Registrar medidas e consultar avaliações.' },
            { value: 'events', label: 'Eventos', detail: 'Organizar inscrições e a experiência dos participantes.' },
        ],
    },
];

const firstSteps = {
    workouts: {
        title: 'Vamos começar pelos treinos',
        description: 'Monte sua primeira ficha e organize a prescrição dos seus alunos.',
        href: '/treinos',
        action: 'Organizar treinos',
    },
    schedule: {
        title: 'Vamos organizar sua agenda',
        description: 'Planeje os próximos horários e aulas da sua operação.',
        href: '/agenda',
        action: 'Abrir agenda',
    },
    finance: {
        title: 'Vamos organizar seus recebimentos',
        description: 'Cadastre um aluno e configure sua mensalidade para começar a acompanhar as cobranças.',
        href: '/alunos/novo',
        action: 'Cadastrar primeiro aluno',
    },
    progress: {
        title: 'Vamos acompanhar cada evolução',
        description: 'Cadastre um aluno para registrar sua primeira avaliação física na ficha dele.',
        href: '/alunos/novo',
        action: 'Cadastrar primeiro aluno',
    },
    events: {
        title: 'Vamos preparar seu primeiro evento',
        description: 'Organize um evento e configure as inscrições dos participantes.',
        href: '/eventos',
        action: 'Organizar eventos',
    },
};
export const getFirstStep = (profile) => firstSteps[profile?.priorities?.[0]] ?? null;
export const describeAnswer = (key, value) =>
    questions.find((q) => q.key === key)?.options.find((o) => o.value === value)?.label ?? '';
