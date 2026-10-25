const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.WNFIT_BROWSER_CHANNEL || 'chrome' });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const output = process.env.WNFIT_PREVIEW_DIR || 'storage/app/register-preview';
    fs.mkdirSync(output, { recursive: true });
    let requests = 0,
        signedIn = false,
        mode = 'email',
        organization;
    let emailChecks = 0,
        emailMode = 'exists';
    const failures = [];
    page.on('pageerror', (error) => failures.push(error.message));
    await page.route('**/api/**', async (route) => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/api/register/check-email') {
            emailChecks++;
            assert.deepEqual(Object.keys(route.request().postDataJSON()), ['email']);
            if (emailMode === 'network') return route.abort('failed');
            if (emailMode === 'rate') return route.fulfill({ status: 429, json: { message: 'Too many attempts' } });
            await new Promise((resolve) => setTimeout(resolve, 200));
            return route.fulfill({ json: { available: emailMode !== 'exists' } });
        }
        if (path === '/api/register') {
            requests++;
            const payload = route.request().postDataJSON();
            assert.equal(payload.company_type, undefined);
            assert.equal(payload.password, 'Senha123!');
            assert.equal(payload.phone, '71999991234');
            assert.deepEqual(payload.onboarding_profile, {
                student_range: '21_50',
                service_mode: 'hybrid',
                team: 'team',
                priorities: ['workouts', 'progress'],
            });
            if (mode === 'network') return route.abort('failed');
            const errors =
                mode === 'email'
                    ? { email: ['Já existe uma conta com este e-mail.'] }
                    : mode === 'profile'
                      ? { 'onboarding_profile.service_mode': ['Confira sua forma de atendimento.'] }
                      : mode === 'company'
                        ? { company: ['Confira o nome do ambiente.'] }
                        : null;
            if (errors) return route.fulfill({ status: 422, json: { errors } });
            await new Promise((resolve) => setTimeout(resolve, 400));
            signedIn = true;
            organization = { name: payload.company, type: null, onboarding_profile: payload.onboarding_profile };
            return route.fulfill({ status: 201, json: { csrf: 'test', user: { name: payload.name }, organization } });
        }
        if (path === '/api/dashboard')
            return route.fulfill({
                json: { stats: [{ label: 'Alunos', value: '0' }], chart: [], classes: [], clients: [], incomeBars: [] },
            });
        return route.fulfill({
            json: {
                csrf: 'test',
                user: signedIn ? { name: 'Maria Teste' } : null,
                organization: signedIn ? organization : null,
            },
        });
    });
    const next = async () => {
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await page.getByRole('button', { name: 'Verificando e-mail...' }).waitFor({ state: 'hidden' });
    };
    const back = () => page.getByRole('button', { name: 'Voltar', exact: true }).click();
    const submit = () => page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    const layouts = new Map();
    const shot = async (size, step) => {
        const layout = await page.evaluate(() => {
            const rect = (selector) => {
                const r = document.querySelector(selector).getBoundingClientRect();
                return { top: r.top, bottom: r.bottom };
            };
            return {
                title: rect('h1'),
                heading: rect('.register-heading'),
                description: rect('.register-heading > p:last-child'),
                progress: rect('ol'),
                content: rect('.register-content'),
                actions: rect('.register-actions'),
                height: innerHeight,
                pageHeight: document.documentElement.scrollHeight,
            };
        });
        assert.ok(layout.description.bottom + 4 <= layout.progress.top, size + ': heading cannot overlap progress');
        assert.ok(layout.pageHeight <= layout.height + 1, 'Page itself must not scroll');
        assert.ok(layout.actions.bottom <= layout.height, 'Actions stay in viewport');
        assert.ok(layout.content.bottom <= layout.actions.top, 'Content cannot cover actions');
        assert.ok(layout.content.bottom - layout.content.top >= 80, 'Scrollable content remains usable');
        const baseline = layouts.get(size);
        if (baseline) {
            for (const key of ['title', 'progress', 'content', 'actions'])
                assert.ok(Math.abs(layout[key].top - baseline[key].top) <= 1, size + ': fixed ' + key);
        } else layouts.set(size, layout);
        await page.screenshot({ path: `${output}/${size}-${step}.png`, fullPage: true });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    };
    await page.goto(`${process.env.WNFIT_BASE_URL || 'http://127.0.0.1:8000'}/cadastro`);
    await page.getByRole('heading', { name: 'Vamos começar por você' }).waitFor();
    await next();
    assert.equal(await page.locator('[aria-invalid="true"]').count(), 2);
    for (const [name, value] of Object.entries({
        name: 'Maria Teste',
        email: 'maria@example.com',
    }))
        await page.locator(`[name="${name}"]`).fill(value);
    await shot('desktop', 1);
    assert.equal(emailChecks, 0);
    await page.locator('[name="email"]').press('Enter');
    await page.getByRole('dialog').waitFor();
    assert.equal(await page.getByRole('link', { name: 'Ir para login', exact: true }).getAttribute('href'), '/entrar');
    assert.equal(requests, 0);
    assert.equal(
        await page.getByRole('button', { name: 'Corrigir e-mail' }).evaluate((el) => el === document.activeElement),
        true,
    );
    await page.keyboard.press('Shift+Tab');
    assert.equal(
        await page.getByRole('link', { name: 'Ir para login' }).evaluate((el) => el === document.activeElement),
        true,
    );
    await page.screenshot({ path: output + '/existing-email.png', fullPage: true });
    await page.setViewportSize({ width: 320, height: 568 });
    const dialogBox = await page.getByRole('dialog').boundingBox();
    assert.ok(dialogBox.x >= 0 && dialogBox.x + dialogBox.width <= 320);
    assert.ok(dialogBox.y >= 0 && dialogBox.y + dialogBox.height <= 568);
    await page.screenshot({ path: output + '/existing-email-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('[name="email"]').evaluate((el) => el === document.activeElement), true);
    await next();
    await page.getByRole('button', { name: 'Corrigir e-mail' }).click();
    await page.locator('[name="email"]').fill('novo@example.com');
    assert.equal(await page.getByRole('dialog').count(), 0);
    emailMode = 'network';
    await next();
    await page.getByRole('alert').filter({ hasText: 'verificar seu e-mail' }).waitFor();
    emailMode = 'rate';
    await next();
    await page.getByRole('alert').filter({ hasText: 'Aguarde um minuto' }).waitFor();
    emailMode = 'available';
    const beforeChecks = emailChecks;
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.locator('form').evaluate((el) => {
        el.requestSubmit();
        el.requestSubmit();
    });
    assert.equal(await page.getByRole('button', { name: 'Verificando e-mail...' }).isDisabled(), true);
    await page.getByRole('heading', { name: 'Proteja sua conta' }).waitFor();
    assert.equal(emailChecks, beforeChecks + 1);
    assert.equal(await page.locator('[name="name"]').count(), 0);
    assert.equal(requests, 0);
    await next();
    assert.equal(await page.locator('[aria-invalid="true"]').count(), 2);
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 0);
    await page.locator('[name="password"]').fill('curta');
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 0);
    await page.locator('[name="password"]').fill('Senha123!');
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 1);
    await page.locator('[name="password_confirmation"]').fill('diferente');
    await next();
    await page.locator('#error-password_confirmation').waitFor();
    await page.locator('[name="password_confirmation"]').fill('Senha123!');
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 2);
    await page.locator('[name="password"]').fill('outra');
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 0);
    await page.locator('[name="password"]').fill('Senha123!');
    assert.equal(await page.locator('.password-requirements .fulfilled').count(), 2);
    await shot('desktop', 2);
    await page.locator('[name="password_confirmation"]').press('Enter');
    await page.getByRole('heading', { name: 'Quantos alunos você acompanha hoje?' }).waitFor();
    assert.equal(await page.locator('h1').evaluate((el) => el === document.activeElement), true);
    await next();
    await page.locator('#error-student_range').waitFor();
    await page.locator('[value="starting"]').check();
    await page.locator('[value="starting"]').focus();
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.locator('[value="1_20"]').isChecked(), true);
    await page.locator('[value="21_50"]').check();
    await shot('desktop', 3);
    await next();
    await next();
    await page.locator('#error-service_mode').waitFor();
    await page.locator('[value="hybrid"]').check();
    await shot('desktop', 4);
    await next();
    await next();
    await page.locator('#error-team').waitFor();
    await page.locator('[value="team"]').check();
    await shot('desktop', 5);
    await next();
    await next();
    await page.locator('#error-priorities').waitFor();
    for (const v of ['workouts', 'progress', 'schedule']) await page.locator(`[value="${v}"]`).check();
    assert.equal(await page.locator('[value="finance"]').isDisabled(), true);
    await page.locator('[value="schedule"]').uncheck();
    assert.equal(await page.locator('[value="finance"]').isDisabled(), false);
    await shot('desktop', 6);
    await next();
    await next();
    await page.locator('#error-company').waitFor();
    assert.equal(requests, 0);
    await page.locator('[name="company"]').fill('Ambiente Teste');
    await page.locator('[name="phone"]').fill('123');
    await next();
    await page.locator('#error-phone').waitFor();
    await page.locator('[name="phone"]').fill('ab(71) 99999-1234');
    assert.equal(await page.locator('[name="phone"]').inputValue(), '71999991234');
    assert.equal(await page.locator('[name="terms"]').count(), 0);
    assert.equal(requests, 0);
    await shot('desktop', 7);
    await next();
    await page.getByRole('heading', { name: 'Confira seus dados' }).waitFor();
    assert.equal(requests, 0);
    assert.equal((await page.locator('dl').innerText()).includes('Senha123!'), false);
    assert.match(await page.locator('dl').innerText(), /21 a 50 alunos/);
    assert.match(await page.locator('dl').innerText(), /Ambiente Teste/);
    assert.match(await page.locator('dl').innerText(), /71999991234/);
    await page.locator('[name="terms"]').check();
    await shot('desktop', 8);
    await page.setViewportSize({ width: 390, height: 844 });
    for (let step = 8; step >= 1; step--) {
        await shot('mobile', step);
        if (step > 1) await back();
    }
    for (const [name, width, height] of [
        ['small', 320, 568],
        ['landscape', 844, 390],
    ]) {
        await page.setViewportSize({ width, height });
        for (let n = 1; n <= 8; n++) {
            await shot(name, n);
            if (n < 8) await next();
        }
        for (let n = 8; n > 1; n--) await back();
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await next();
    assert.equal(await page.locator('[name="password"]').inputValue(), 'Senha123!');
    for (let i = 2; i < 7; i++) await next();
    assert.equal(await page.locator('[name="company"]').inputValue(), 'Ambiente Teste');
    await next();
    await page.locator('[name="terms"]').uncheck();
    await submit();
    assert.equal(requests, 0);
    await page.locator('[name="terms"]').check();
    await submit();
    await page.locator('#error-email').waitFor();
    await page.getByRole('button', { name: 'Corrigir e-mail' }).click();
    mode = 'profile';
    for (let i = 1; i < 8; i++) await next();
    await submit();
    await page.locator('#error-service_mode').waitFor();
    mode = 'company';
    for (let i = 4; i < 8; i++) await next();
    await submit();
    await page.locator('#error-company').waitFor();
    mode = 'network';
    await next();
    await submit();
    await page.getByRole('alert').filter({ hasText: 'conexão' }).waitFor();
    mode = 'success';
    const before = requests;
    await submit();
    await page.locator('form').evaluate((el) => {
        el.requestSubmit();
        el.requestSubmit();
    });
    assert.equal(await page.getByRole('button', { name: 'Criando...' }).isDisabled(), true);
    await page.waitForURL('**/painel');
    assert.equal(requests, before + 1);
    await page.getByRole('link', { name: 'Organizar treinos' }).waitFor();
    assert.equal(
        await page.evaluate(() =>
            [...Object.values(localStorage), ...Object.values(sessionStorage)].some((v) => v.includes('Senha123!')),
        ),
        false,
    );
    assert.deepEqual(failures, []);
    await page.screenshot({ path: `${output}/welcome.png`, fullPage: true });
    await browser.close();
    console.log(
        'PASS: eight screens, validation, keyboard, max priorities, memory, nested API errors, retry, single submission, tailored dashboard, desktop/mobile overflow and screenshots.',
    );
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
