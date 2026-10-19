import makeWASocket, { DisconnectReason, useMultiFileAuthState } from '@whiskeysockets/baileys';
import pino from 'pino';
import qr from 'qrcode-terminal';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { createGateway } from './gateway.js';

process.umask(0o077);
const directory = resolve(process.env.WHATSAPP_DATA_DIR || './data');
await mkdir(directory, { recursive: true, mode: 0o700 });
const logger = pino({ level: 'silent' });
let socket;
let connected = false;
let reconnect;
let stopping = false;
const adapter = {
  ready: () => connected,
  async send(to, body) {
    const result = await socket.sendMessage(`${to.replace(/\D/g, '')}@s.whatsapp.net`, { text: body });
    return result?.key?.id;
  },
};
const server = createGateway({ token: process.env.BRIDGE_TOKEN, database: `${directory}/sends.sqlite`, adapter });
server.listen(Number(process.env.PORT || 3000), process.env.HOST || '0.0.0.0');
async function connect() {
  const { state, saveCreds } = await useMultiFileAuthState(`${directory}/auth`);
  socket = makeWASocket({ auth: state, logger, markOnlineOnConnect: false, syncFullHistory: false });
  socket.ev.on('creds.update', saveCreds);
  socket.ev.on('connection.update', update => {
    if (update.qr && process.env.BRIDGE_SHOW_QR === 'true') {
      // Enable only while pairing; the QR grants account access.
      qr.generate(update.qr, { small: true });
    }
    if (update.connection === 'open') {
      connected = true;
      console.info('WhatsApp connected');
    }
    if (update.connection === 'close') {
      connected = false;
      const loggedOut = update.lastDisconnect?.error?.output?.statusCode === DisconnectReason.loggedOut;
      console.info(loggedOut ? 'WhatsApp logged out: pairing required' : 'WhatsApp disconnected');
      if (!loggedOut && !stopping) reconnect = setTimeout(() => connect().catch(() => process.exit(1)), 5000);
    }
  });
}
await connect();
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => {
  stopping = true;
  clearTimeout(reconnect);
  connected = false;
  socket?.end(undefined);
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 10000).unref();
});
