import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolveRecipient, safeConsoleArgs } from '../src/recipient.js';

test('uses canonical WhatsApp recipient instead of assuming phone JID', async () => {
  assert.equal(await resolveRecipient({ onWhatsApp: async phone => {
    assert.equal(phone, '+5571999887766');
    return [{ exists: true, jid: 'canonical@s.whatsapp.net' }];
  } }, '+5571999887766'), 'canonical@s.whatsapp.net');
});
test('unregistered phone is rejected before sending', async () => {
  await assert.rejects(resolveRecipient({ onWhatsApp: async () => [] }, '+5571999887766'));
});
test('session objects containing keys never reach console', () => {
  assert.deepEqual(safeConsoleArgs(['Closing session:', { privateKey: 'secret' }]), ['Closing session:']);
});
