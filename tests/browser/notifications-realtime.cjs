// Run against a local Laravel server and Reverb. No database writes.
const { chromium } = require('playwright');
const { readFileSync } = require('node:fs');
const { createHash, createHmac } = require('node:crypto');
const assert = require('node:assert/strict');
const env = Object.fromEntries(readFileSync('.env', 'utf8').split(/\r?\n/).filter((line) => /^[A-Z_]+=/.test(line)).map((line) => {
    const at = line.indexOf('=');
    return [line.slice(0, at), line.slice(at + 1).replace(/^["']|["']$/g, '')];
}));
const sign = (value) => createHmac('sha256', env.REVERB_APP_SECRET).update(value).digest('hex');
(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'chrome' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        let subscribed = false, changed = false, requests = 0;
        page.on('websocket', (socket) => socket.on('framereceived', ({ payload }) => {
            if (String(payload).includes('pusher_internal:subscription_succeeded')) subscribed = true;
        }));
        const item = { id: 900001, type: 'public_booking_created', readAt: null, createdAt: new Date().toISOString(), data: {
            guestName: 'Visitante QA', appointmentTitle: 'Aula experimental', startsAt: new Date().toISOString(), endsAt: new Date().toISOString(),
            phone: '11999999999', instructorName: 'Professor QA', href: '/agenda',
        } };
        await page.route('**/broadcasting/auth', (route) => {
            const { socket_id, channel_name } = route.request().postDataJSON();
            return route.fulfill({ json: { auth: env.REVERB_APP_KEY + ':' + sign(socket_id + ':' + channel_name) } });
        });
        await page.route('**/api/**', (route) => {
            const path = new URL(route.request().url()).pathname;
            if (path === '/api/notifications') {
                requests++;
                return route.fulfill({ json: { notifications: changed ? [item] : [], unreadCount: changed ? 1 : 0, nextCursor: null } });
            }
            return route.fulfill({ json: path === '/api/dashboard'
                ? { stats: [], chart: [], classes: [], clients: [], incomeBars: [] }
                : { csrf: 'qa', user: { id: 900001, name: 'Gestor QA' }, organization: { id: 900001, name: 'QA' } } });
        });
        await page.goto('http://127.0.0.1:8000/painel');
        for (let i = 0; i < 50 && !subscribed; i++) await page.waitForTimeout(100);
        assert.ok(subscribed, 'Private WebSocket subscription must succeed');
        await page.waitForTimeout(500);
        const initial = requests;
        await page.waitForTimeout(31000);
        assert.equal(requests, initial, 'No notification polling while idle');
        changed = true;
        const path = '/apps/' + env.REVERB_APP_ID + '/events';
        const body = JSON.stringify({ name: 'staff.notification.changed', channels: ['private-staff.org.900001.user.900001'], data: JSON.stringify({ action: 'created', organizationId: 900001, userId: 900001, notification: item }) });
        const query = new URLSearchParams({ auth_key: env.REVERB_APP_KEY, auth_timestamp: String(Math.floor(Date.now() / 1000)), auth_version: '1.0', body_md5: createHash('md5').update(body).digest('hex') });
        query.sort();
        const signature = sign('POST\n' + path + '\n' + query.toString());
        const response = await fetch('http://127.0.0.1:8081' + path + '?' + query + '&auth_signature=' + signature, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
        assert.ok(response.ok, 'Reverb must accept the signed event');
        await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).waitFor();
        assert.equal(requests, initial + 1, 'One event-driven history refresh');
        await page.getByRole('button', { name: 'Abrir notificações, 1 não lida', exact: true }).click();
        await page.getByRole('button', { name: 'Abrir novo agendamento de Visitante QA', exact: true }).waitFor();
        assert.deepEqual(errors, []);
        console.log('PASS: real private WebSocket, no idle polling, booking badge and history.');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error.message); process.exit(1); });
