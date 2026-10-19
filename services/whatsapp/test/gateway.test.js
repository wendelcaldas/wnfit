import { test } from 'node:test';
import assert from 'node:assert/strict';
import { once } from 'node:events';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createGateway } from '../src/gateway.js';

const token = 'test-token-'.repeat(4);
const message = { key: 'welcome:1:2', to: '+5571999887766', body: 'Boas-vindas!' };
async function fixture(t, adapter, database = ':memory:') {
  const server = createGateway({ token, database, adapter });
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  t.after(() => new Promise(resolve => { server.close(resolve); server.closeAllConnections(); }));
  return (data = message, auth = token) => fetch(`http://127.0.0.1:${server.address().port}/welcome`, {
    method: 'POST', headers: { authorization: `Bearer ${auth}` }, body: JSON.stringify(data),
  });
}
test('auth and validation prevent sending', async t => {
  let calls = 0;
  const send = await fixture(t, { ready: () => true, send: async () => { calls++; return 'id'; } });
  assert.equal((await send(message, 'wrong')).status, 401);
  assert.equal((await send({ ...message, to: '123' })).status, 422);
  assert.equal((await send({ ...message, key: 'charge:1:2' })).status, 422);
  assert.equal(calls, 0);
});
test('same key sends once and conflicting content is rejected', async t => {
  let calls = 0;
  const send = await fixture(t, { ready: () => true, send: async () => { calls++; return 'wa-id'; } });
  assert.equal((await send()).status, 200);
  assert.deepEqual(await (await send()).json(), { id: 'wa-id' });
  assert.equal((await send({ ...message, body: 'Changed' })).status, 409);
  assert.equal(calls, 1);
});
test('disconnection is retryable without reserving a send', async t => {
  let ready = false;
  const send = await fixture(t, { ready: () => ready, send: async () => 'id' });
  assert.equal((await send()).status, 503);
  ready = true;
  assert.equal((await send()).status, 200);
});
test('uncertain send is never retried, including across restart', async t => {
  const directory = mkdtempSync(join(tmpdir(), 'wnfit-wa-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  let calls = 0;
  const database = join(directory, 'sends.sqlite');
  const send = await fixture(t, { ready: () => true, send: async () => { calls++; throw new Error('connection lost'); } }, database);
  assert.equal((await send()).status, 409);
  const restarted = await fixture(t, { ready: () => true, send: async () => { calls++; return 'duplicate'; } }, database);
  assert.equal((await restarted()).status, 409);
  assert.equal(calls, 1);
});
test('concurrent request for same key cannot duplicate', async t => {
  let release;
  const send = await fixture(t, { ready: () => true, send: () => new Promise(resolve => { release = resolve; }) });
  const first = send();
  while (!release) await new Promise(resolve => setImmediate(resolve));
  assert.equal((await send()).status, 409);
  assert.equal((await send({ ...message, key: 'welcome:1:3' })).status, 429);
  release('id');
  assert.equal((await first).status, 200);
});

test('pairing QR requires authentication and stays internal', async t => {
  const server = createGateway({ token, database: ':memory:', adapter: { ready: () => false, pairing: () => 'pairing-secret' } });
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  t.after(() => new Promise(resolve => { server.close(resolve); server.closeAllConnections(); }));
  const url = `http://127.0.0.1:${server.address().port}/pairing`;
  assert.equal((await fetch(url)).status, 401);
  const response = await fetch(url, { headers: { authorization: `Bearer ${token}` } });
  assert.deepEqual(await response.json(), { connected: false, qr: 'pairing-secret' });
});
