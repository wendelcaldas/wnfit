const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'chrome' });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    let read = false,
        failRead = false;
    const notification = () => ({
        id: 42,
        type: 'workout_completed',
        readAt: read ? '2026-10-25T15:00:00Z' : null,
        createdAt: '2026-10-25T14:55:00Z',
        data: {
            studentName: 'Aluno de QA',
            studentId: 123,
            sessionId: 123,
            dayName: 'Treino A',
            workoutName: 'Programa ABC',
            focus: 'Peito',
            completedAt: '2026-10-25T14:55:00Z',
            durationSeconds: 2280,
            completedExercises: 0,
            totalExercises: 1,
            completedSets: 1,
            totalSets: 2,
            href: '/alunos/123?tab=workouts',
        },
    });
    await page.route('**/api/**', (route) => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/api/notifications/42/read') {
            if (failRead) return route.fulfill({ status: 500, json: { message: 'error' } });
            read = true;
            return route.fulfill({ json: { notification: notification(), unreadCount: 0 } });
        }
        if (path === '/api/notifications')
            return route.fulfill({
                json: { notifications: [notification()], unreadCount: read ? 0 : 1, nextCursor: null },
            });
        return route.fulfill({
            json:
                path === '/api/dashboard'
                    ? { stats: [], chart: [], classes: [], clients: [], incomeBars: [] }
                    : {
                          csrf: 'demo',
                          user: { id: 1, name: 'Gestor de demonstração' },
                          organization: { id: 1, name: 'Ambiente de demonstração' },
                      },
        });
    });
    fs.mkdirSync('storage/app/notification-preview', { recursive: true });
    await page.goto('http://127.0.0.1:8000/painel?previewNotifications=1');
    await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).waitFor();
    await page.screenshot({
        path: 'storage/app/notification-preview/bell.png',
        clip: { x: 600, y: 0, width: 680, height: 280 },
    });
    await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).click();
    await page.getByRole('dialog', { name: 'Notificações', exact: true }).waitFor();
    await page.screenshot({
        path: 'storage/app/notification-preview/list.png',
        clip: { x: 600, y: 0, width: 680, height: 390 },
    });
    await page.getByRole('button', { name: 'Abrir treino concluído de Mariana', exact: true }).click();
    assert.equal(await page.locator('.notification-badge').count(), 0);
    await page.getByRole('heading', { name: 'Mariana finalizou o treino A' }).waitFor();
    await page.screenshot({
        path: 'storage/app/notification-preview/detail.png',
        clip: { x: 600, y: 0, width: 680, height: 540 },
    });
    await page.getByRole('button', { name: 'Voltar à lista', exact: true }).click();
    await page.getByText('Há 5 minutos · Lida', { exact: true }).waitFor();
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Abrir notificações', exact: true }).click();
    const box = await page.getByRole('dialog', { name: 'Notificações', exact: true }).boundingBox();
    assert.ok(box.x >= 0 && box.x + box.width <= 390 && box.y + box.height <= 844);
    await page.screenshot({ path: 'storage/app/notification-preview/mobile.png', fullPage: true });
    await page.goto('http://127.0.0.1:8000/painel');
    await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir treino concluído de Aluno de QA', exact: true }).waitFor();
    failRead = true;
    await page.getByRole('button', { name: 'Abrir treino concluído de Aluno de QA', exact: true }).click();
    await page.getByRole('alert').filter({ hasText: 'Não foi possível marcar como lida' }).waitFor();
    assert.equal(await page.locator('.notification-badge').innerText(), '1');
    failRead = false;
    await page.getByRole('button', { name: 'Tentar novamente', exact: true }).click();
    await page.locator('.notification-badge').waitFor({ state: 'hidden' });
    assert.equal(
        await page.getByRole('link', { name: 'Ver treinos do aluno', exact: true }).getAttribute('href'),
        '/alunos/123?tab=workouts',
    );
    await page.screenshot({ path: 'storage/app/notification-preview/real-detail.png', fullPage: true });
    await page.keyboard.press('Escape');
    await page.reload();
    await page.getByRole('button', { name: 'Abrir notificações', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir treino concluído de Aluno de QA', exact: true }).waitFor();
    assert.equal(await page.locator('.notification-row.is-unread').count(), 0);
    assert.equal(await page.locator('.notification-preview-note').count(), 0);
    assert.deepEqual(errors, []);
    await browser.close();
    console.log('PASS: preview badge, list, detail, read state, Escape and mobile bounds.');
})().catch((e) => {
    console.error(e);
    process.exit(1);
});
