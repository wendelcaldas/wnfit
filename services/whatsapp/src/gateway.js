import { createServer } from 'node:http';
import { timingSafeEqual, createHash } from 'node:crypto';
import { DatabaseSync } from 'node:sqlite';

export function createGateway({ token, database, adapter }) {
  if (!token || token.length < 32) throw new Error('BRIDGE_TOKEN must have at least 32 characters');
  const db = new DatabaseSync(database);
  db.exec(`PRAGMA journal_mode=WAL;
    CREATE TABLE IF NOT EXISTS sends (key TEXT PRIMARY KEY, fingerprint TEXT NOT NULL, state TEXT NOT NULL, id TEXT)`);
  let busy = false;
  const digest = value => createHash('sha256').update(value).digest();
  const server = createServer(async (req, res) => {
    const reply = (status, body) => {
      res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
      res.end(JSON.stringify(body));
    };
    if (!timingSafeEqual(digest(req.headers.authorization || ''), digest(`Bearer ${token}`))) return reply(401, { error: 'unauthorized' });
    if (req.method === 'GET' && req.url === '/health') return reply(adapter.ready() ? 200 : 503, { connected: adapter.ready() });
    if (req.method !== 'POST' || req.url !== '/welcome') return reply(404, { error: 'not_found' });
    try {
      let raw = '';
      for await (const chunk of req) {
        raw += chunk;
        if (Buffer.byteLength(raw) > 8192) return reply(413, { error: 'too_large' });
      }
      let data;
      try { data = JSON.parse(raw); } catch { return reply(400, { error: 'invalid_json' }); }
      const { key, to, body } = data || {};
      if (typeof key !== 'string' || !/^welcome:\d+:\d+$/.test(key) || key.length > 100 ||
          typeof to !== 'string' || !/^\+55[1-9]\d{9,10}$/.test(to) ||
          typeof body !== 'string' || !body.trim() || body.length > 2000) return reply(422, { error: 'invalid_welcome' });
      const fingerprint = digest(`${to}\n${body}`).toString('hex');
      const previous = db.prepare('SELECT * FROM sends WHERE key = ?').get(key);
      if (previous) {
        if (previous.fingerprint !== fingerprint || previous.state !== 'sent') return reply(409, { error: 'uncertain_or_conflicting' });
        return reply(200, { id: previous.id });
      }
      if (!adapter.ready()) return reply(503, { error: 'disconnected' });
      if (busy) return reply(429, { error: 'busy' });
      busy = true;
      try {
        // Commit before the external side effect. After a crash, never blindly resend.
        db.prepare("INSERT INTO sends (key, fingerprint, state) VALUES (?, ?, 'sending')").run(key, fingerprint);
        let deadline;
        let id;
        try {
          id = await Promise.race([
            adapter.send(to, body),
            new Promise((_, reject) => { deadline = setTimeout(() => reject(new Error('Send timeout')), 25000); }),
          ]);
        } finally { clearTimeout(deadline); }
        if (!id) throw new Error('No message id');
        db.prepare("UPDATE sends SET state = 'sent', id = ? WHERE key = ?").run(id, key);
        return reply(200, { id });
      } catch {
        return reply(409, { error: 'uncertain' });
      } finally { busy = false; }
    } catch {
      if (!res.headersSent) reply(500, { error: 'internal' });
    }
  });
  server.requestTimeout = 15000;
  server.headersTimeout = 10000;
  server.on('close', () => db.close());
  return server;
}
