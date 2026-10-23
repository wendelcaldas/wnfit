const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        channel: process.env.WNFIT_BROWSER_CHANNEL || 'chrome',
    });
    const page = await browser.newPage();
    const output = process.env.WNFIT_PREVIEW_DIR || 'storage/app/register-preview';
    fs.mkdirSync(output, { recursive: true });
    let requests = 0;
    let mode = 'email';
    let signedIn = false;
    await page.route('**/api/**', async (route) => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/api/register') {
            requests++;
            const payload = route.request().postDataJSON();
            assert.equal(payload.password, 'Senha123!');
            assert.equal(payload.company_type, 'studio');
            if (mode === 'network') return route.abort('failed');
            if (mode === 'email')
                return route.fulfill({
                    status: 422,
                    json: {
                        errors: {
                            email: ['Já existe uma conta com este e-mail.'],
                        },
                    },
                });
            if (mode === 'business')
                return route.fulfill({
                    status: 422,
                    json: {
                        errors: { company: ['Confira o nome do ambiente.'] },
                    },
                });
            await new Promise((resolve) => setTimeout(resolve, 400));
            signedIn = true;
            return route.fulfill({
                status: 201,
                json: {
                    csrf: 'test',
                    user: { name: payload.name },
                    organization: { name: payload.company },
                },
            });
        }
        return route.fulfill({
            json: {
                csrf: 'test',
                user: signedIn ? { name: 'Maria Teste' } : null,
                organization: signedIn ? { name: 'Studio Teste' } : null,
            },
        });
    });
    await page.goto('http://127.0.0.1:8091/cadastro');
    await page.getByRole('heading', { name: 'Sua conta' }).waitFor();
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    assert.equal(await page.locator('[aria-invalid="true"]').count(), 4);
    for (const [name, value] of Object.entries({
        name: 'Maria Teste',
        email: 'maria@example.com',
        password: 'Senha123!',
        password_confirmation: 'diferente',
    }))
        await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[name="password_confirmation"]').press('Enter');
    assert.equal(await page.getByRole('heading').last().textContent(), 'Sua conta');
    await page.locator('[name="password_confirmation"]').fill('Senha123!');
    await page.screenshot({ path: `${output}/desktop-1.png`, fullPage: true });
    await page.locator('[name="password_confirmation"]').press('Enter');
    await page.getByRole('heading', { name: 'Seu negócio' }).waitFor();
    assert.equal(await page.locator('h1').evaluate((el) => el === document.activeElement), true);
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    assert.equal(await page.locator('[aria-invalid="true"]').count(), 2);
    await page.locator('[name="company"]').fill('Studio Teste');
    await page.locator('[name="company_type"]').selectOption('studio');
    await page.locator('[name="phone"]').fill('(71) 99999-1234');
    await page.screenshot({ path: `${output}/desktop-2.png`, fullPage: true });
    await page.getByRole('button', { name: 'Voltar', exact: true }).click();
    assert.equal(await page.locator('[name="password"]').inputValue(), 'Senha123!');
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    assert.equal(await page.locator('[name="company"]').inputValue(), 'Studio Teste');
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    assert.equal(requests, 0);
    assert.equal(
        await page
            .locator('dl')
            .innerText()
            .then((text) => text.includes('Senha123!')),
        false,
    );
    await page.screenshot({ path: `${output}/desktop-3.png`, fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    for (let step = 3; step >= 1; step--) {
        await page.screenshot({
            path: `${output}/mobile-${step}.png`,
            fullPage: true,
        });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        if (step > 1) await page.getByRole('button', { name: 'Voltar', exact: true }).click();
    }
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    assert.equal(requests, 0);
    await page.locator('[name="terms"]').check();
    await page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    await page.getByRole('heading', { name: 'Sua conta' }).waitFor();
    assert.match(await page.locator('#error-email').textContent(), /Já existe/);
    assert.equal(await page.locator('[name="password"]').inputValue(), 'Senha123!');
    mode = 'business';
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    await page.getByRole('heading', { name: 'Seu negócio' }).waitFor();
    await page.locator('#error-company').waitFor();
    mode = 'network';
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    await page.getByRole('alert').filter({ hasText: 'conexão' }).waitFor();
    mode = 'success';
    const before = requests;
    await page.getByRole('button', { name: 'Criar meu ambiente', exact: true }).click();
    await page.locator('form').evaluate((el) => {
        el.requestSubmit();
        el.requestSubmit();
    });
    assert.equal(await page.getByRole('button', { name: 'Criando...' }).isDisabled(), true);
    await page.waitForURL('**/painel');
    assert.equal(requests, before + 1);
    assert.equal(
        await page.evaluate(() =>
            Object.values(localStorage)
                .concat(Object.values(sessionStorage))
                .some((value) => value.includes('Senha123!')),
        ),
        false,
    );
    await browser.close();
    console.log(
        'PASS: validation, keyboard, memory, review, field errors, network retry, single submission, redirect, desktop/mobile screenshots.',
    );
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
