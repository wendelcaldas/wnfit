// Captures the real local Vue interface using isolated, fictional API responses.
// Never submits forms or reads customer data. Run with the local app and Vite available.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const output = path.resolve('public/images/support');
const dimensions = new Map();
const base = process.env.HELP_CAPTURE_URL || 'http://127.0.0.1:8000';
const plans = [{ id: 1, name: 'Plano Mensal', cycle: 'mensal', value: 180, active: true, subscriptions: 0 }];
const users = [{ id: 900001, name: 'Marina Exemplo', email: 'marina@example.com', role: 'proprietario', status: 'ativo', initials: 'ME' }, { id: 900002, name: 'Paulo Exemplo', email: 'paulo@example.com', role: 'professor', status: 'ativo', initials: 'PE' }];
const hours = Object.fromEntries(Array.from({ length: 7 }, (_, day) => [day, day >= 1 && day <= 5 ? [{ start: '08:00', end: '12:00' }, { start: '14:00', end: '18:00' }] : []]));
const links = [{ id: 1, title: 'Aula experimental', durationMinutes: 60, instructorId: 900002, active: true, location: 'Unidade principal', publicUrl: '/agendar/link-ilustrativo' }];
const workout = { id: 1, name: 'Condicionamento inicial', objective: 'Condicionamento', level: 'Iniciante', sessionsPerWeek: 3, durationWeeks: 4, status: 'ativo', updatedAt: '29/10/2026' };
const fixtures = {
    '/api/me': { csrf: 'illustrative', user: users[0], organization: { id: 900001, name: 'Estúdio Exemplo', role: 'proprietario' } },
    '/api/notifications': { notifications: [], unreadCount: 0, nextCursor: null },
    '/api/organization/plans': { plans, canManage: true },
    '/api/organization/users': { users },
    '/api/organization/units': { units: [{ id: 1, name: 'Unidade principal', active: true, default: true, students: 0 }], canManage: true },
    '/api/organization/schedule-settings': { canManage: true, instructors: users.map(({ id, name }) => ({ id, name })), settings: { enforced: true, weeklyHours: hours, timezone: 'America/Bahia', bookingLinks: links } },
    '/api/students/options': { plans: [{ id: 1, nome: 'Plano Mensal', valor_mensal: 180, ciclo: 'mensal' }], teachers: [{ nome: 'Paulo Exemplo' }], units: [{ id: 1, nome: 'Unidade principal', padrao: true }], defaultUnitId: 1 },
    '/api/student-access/suggest': { username: 'ana.exemplo' },
    '/api/workouts': { workouts: [workout], summary: { total: 1, active: 1, drafts: 0, archived: 0 }, filters: { objectives: ['Condicionamento'] } },
    '/api/students': { students: [{ id: 1, name: 'Ana Exemplo' }] },
};
(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, channel: 'chrome' });
    try {
        const page = await browser.newPage({ viewport: { width: 1500, height: 1100 }, deviceScaleFactor: 1 });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/api/**', (route) => {
            const request = route.request();
            const url = new URL(request.url());
            if (request.method() !== 'GET' || !fixtures[url.pathname]) {
                errors.push('Unexpected API request: ' + request.method() + ' ' + url.pathname);
                return route.abort();
            }
            return route.fulfill({ json: fixtures[url.pathname] });
        });
        await page.route('**/broadcasting/auth', (route) => route.fulfill({ status: 403, json: {} }));
        await page.route('https://mcp.figma.com/**', (route) => route.abort());
        const reset = async () => page.evaluate(() => {
            document.querySelectorAll('[data-guide-marker]').forEach((node) => node.remove());
            document.querySelectorAll('[data-guide-highlight]').forEach((node) => { node.style.outline = ''; node.style.outlineOffset = ''; node.removeAttribute('data-guide-highlight'); });
        });
        async function mark(locator, number) {
            await locator.evaluate((element, number) => {
                element.dataset.guideHighlight = 'true';
                element.style.outline = '2px solid #668a27';
                element.style.outlineOffset = '3px';
                const box = element.getBoundingClientRect();
                const badge = document.createElement('span');
                badge.dataset.guideMarker = 'true';
                badge.textContent = number;
                badge.style.cssText = 'position:absolute;z-index:9999;width:24px;height:24px;display:grid;place-items:center;border-radius:50%;background:#354d22;color:white;border:2px solid white;font:700 12px sans-serif;box-shadow:0 2px 6px #0002;pointer-events:none;';
                badge.style.left = Math.max(0, box.left + scrollX - 12) + 'px';
                badge.style.top = Math.max(0, box.top + scrollY - 12) + 'px';
                (element.closest('dialog[open]') || document.body).appendChild(badge);
            }, number);
        }
        async function visit(url, heading) {
            await page.goto(base + url);
            await page.getByRole('heading', { name: heading, exact: true }).waitFor();
            await page.evaluate(() => document.fonts.ready);
        }
        async function capture(name, target = null) {
            await page.evaluate(() => document.activeElement?.blur());
            const box = target ? await target.boundingBox() : await page.locator('.dashboard-content').boundingBox();
            const clip = target ? { x: Math.max(0, box.x - 18), y: Math.max(0, box.y - 18), width: box.width + 36, height: box.height + 36 }
                : { x: box.x + 12, y: 0, width: 1500 - box.x - 12, height: Math.min(1100, box.y + box.height + 15) };
            const buffer = await page.screenshot({ path: path.join(output, name + '.jpg'), type: 'jpeg', quality: 88, clip });
            // Read JPEG frame dimensions so article aspect ratios stay accurate after recapturing.
            for (let offset = 2; offset < buffer.length; ) {
                const marker = buffer[offset + 1];
                if ([0xc0, 0xc1, 0xc2].includes(marker)) {
                    dimensions.set(name + '.jpg', { width: buffer.readUInt16BE(offset + 7), height: buffer.readUInt16BE(offset + 5) });
                    break;
                }
                offset += 2 + buffer.readUInt16BE(offset + 2);
            }
        }
        await visit('/configuracoes/planos', 'Planos');
        await page.getByText('Plano Mensal', { exact: true }).waitFor();
        await page.getByLabel('Nome do plano').fill('Funcional 3x por semana');
        await page.getByLabel('Valor por mês (R$)').fill('180');
        await mark(page.getByRole('navigation', { name: 'Configurações' }), 1);
        await mark(page.locator('aside form'), 2);
        await capture('configurar-planos');
        await visit('/configuracoes/usuarios', 'Usuários e professores');
        await page.getByText('2 usuários', { exact: true }).waitFor();
        await mark(page.getByRole('navigation', { name: 'Configurações' }), 1);
        await mark(page.getByRole('heading', { name: 'Equipe cadastrada' }).locator('..'), 2);
        await capture('configurar-equipe', page.locator('.dashboard-content aside.panel-card'));
        await visit('/configuracoes/unidades', 'Unidades');
        await page.getByText('Unidade principal', { exact: true }).waitFor();
        await mark(page.getByRole('heading', { name: 'Unidades do seu negócio' }).locator('..'), 1);
        await mark(page.getByRole('button', { name: 'Nova unidade', exact: true }), 2);
        await capture('configurar-unidades');
        await visit('/configuracoes/horarios', 'Horários de funcionamento');
        await page.getByText('Aula experimental', { exact: true }).waitFor();
        await mark(page.locator('.hours-day').first(), 1);
        await mark(page.getByRole('button', { name: 'Salvar alterações' }), 2);
        await capture('configurar-horarios');
        await reset();
        await page.getByRole('button', { name: 'Novo link', exact: true }).click();
        await page.getByRole('dialog').waitFor();
        await page.getByLabel('Atendimento', { exact: true }).fill('Avaliação física');
        await page.getByRole('dialog').locator('select').nth(0).selectOption('900002');
        await page.getByRole('dialog').locator('select').nth(1).selectOption('30');
        await capture('novo-link', page.getByRole('dialog'));
        await visit('/alunos/novo', 'Cadastro rapido');
        await page.getByLabel('Nome completo *', { exact: true }).fill('Ana Exemplo');
        await page.getByLabel('Telefone *', { exact: true }).fill('(11) 99999-0000');
        await page.getByLabel('E-mail', { exact: true }).fill('ana@example.com');
        await page.getByLabel('Usuário do portal', { exact: true }).fill('ana.exemplo');
        await page.getByLabel('Data de inicio *', { exact: true }).fill('2026-10-29');
        await page.getByLabel('Vencimento *', { exact: true }).fill('2026-11-29');
        await page.getByLabel(/^Professor responsavel/).selectOption('Paulo Exemplo');
        await page.getByLabel(/^Modalidade de acompanhamento/).selectOption({ index: 1 });
        const panels = page.locator('form > section.panel-card');
        await mark(panels.nth(0), 1); await mark(panels.nth(1), 2);
        await mark(page.getByRole('button', { name: 'Salvar aluno', exact: true }), 3);
        await capture('cadastro-aluno');
        await reset();
        await capture('cadastro-dados', panels.nth(0));
        await capture('cadastro-matricula', panels.nth(1));
        await visit('/treinos', 'Treinos');
        await page.getByText('Condicionamento inicial', { exact: true }).waitFor();
        await mark(page.getByRole('link', { name: 'Novo treino', exact: true }), 1);
        await mark(page.getByRole('button', { name: 'Entregar ficha', exact: true }), 2);
        await capture('biblioteca-treinos');
        await reset();
        await page.getByRole('button', { name: 'Entregar ficha', exact: true }).click();
        await page.getByRole('dialog').waitFor();
        await page.getByRole('dialog').locator('select').selectOption('1');
        await capture('entregar-ficha', page.getByRole('dialog'));
        if (errors.length) throw Error(errors.join('\n'));
        const contentPath = 'resources/content/help-center.json';
        const content = JSON.parse(fs.readFileSync(contentPath, 'utf8'));
        for (const article of content.articles) {
            for (const section of article.sections) {
                for (const figure of section.figures || []) {
                    const size = dimensions.get(path.basename(figure.src));
                    if (size) Object.assign(figure, size);
                }
            }
        }
        fs.writeFileSync(contentPath, JSON.stringify(content, null, 2) + '\n');
        console.log('PASS: 10 real UI captures with fictional data; no data submitted.');
    } finally { await browser.close(); }
})().catch((error) => { console.error(error.message); process.exit(1); });
