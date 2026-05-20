'use strict';

import { SMTPServer } from 'smtp-server';
import { simpleParser } from 'mailparser';
import fetch from 'node-fetch';

const LARAVEL_INBOUND_URL = process.env.LARAVEL_INBOUND_URL || 'http://app:9000/internal/inbound';
const SMTP_INTERNAL_SECRET = process.env.SMTP_INTERNAL_SECRET || '';
const PORT = parseInt(process.env.SMTP_PORT || '25', 10);
const MAX_MESSAGE_SIZE = 25 * 1024 * 1024; // 25 MB

function log(level, message, extra = {}) {
  process.stdout.write(JSON.stringify({ level, ts: new Date().toISOString(), message, ...extra }) + '\n');
}

/**
 * Forward the parsed email to Laravel with retry logic.
 */
async function forwardToLaravel(parsed, rawBuffer, recipients) {
  const payload = {
    from: parsed.from?.text ?? '',
    from_address: parsed.from?.value?.[0]?.address ?? '',
    from_name: parsed.from?.value?.[0]?.name ?? '',
    to: recipients,
    subject: parsed.subject ?? '(no subject)',
    body_html: parsed.html || null,
    body_text: parsed.text || null,
    headers: Object.fromEntries(
      [...(parsed.headers?.entries?.() ?? [])].map(([k, v]) => [k, String(v)])
    ),
    size_bytes: rawBuffer.length,
  };

  const attempts = 3;
  for (let i = 1; i <= attempts; i++) {
    try {
      const res = await fetch(LARAVEL_INBOUND_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-SMTP-Secret': SMTP_INTERNAL_SECRET,
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(10_000),
      });

      if (res.ok) {
        log('info', 'forwarded', { to: recipients, subject: payload.subject, attempt: i });
        return;
      }

      log('warn', 'laravel_non_ok', { status: res.status, attempt: i });
    } catch (err) {
      log('warn', 'forward_error', { err: err.message, attempt: i });
    }

    if (i < attempts) await new Promise(r => setTimeout(r, 1000 * i));
  }

  log('error', 'forward_failed_all_attempts', { to: recipients });
}

const server = new SMTPServer({
  // Receive-only: disable authentication requirement, accept all
  authOptional: true,
  disabledCommands: ['AUTH'],

  maxMessageSize: MAX_MESSAGE_SIZE,

  // Accept all recipients — Laravel decides whether the alias exists
  onRcptTo(address, session, callback) {
    log('debug', 'rcpt_to', { address: address.address });
    callback();
  },

  onData(stream, session, callback) {
    const chunks = [];

    stream.on('data', chunk => chunks.push(chunk));

    stream.on('end', async () => {
      const rawBuffer = Buffer.concat(chunks);
      const recipients = session.envelope.rcptTo.map(r => r.address);

      try {
        const parsed = await simpleParser(rawBuffer);
        await forwardToLaravel(parsed, rawBuffer, recipients);
      } catch (err) {
        log('error', 'parse_or_forward_error', { err: err.message });
        // Accept anyway — don't bounce legitimate emails due to our own errors
      }

      callback();
    });

    stream.on('error', err => {
      log('error', 'stream_error', { err: err.message });
      callback(err);
    });
  },

  onError(err) {
    log('error', 'smtp_server_error', { err: err.message });
  },
});

server.listen(PORT, '0.0.0.0', () => {
  log('info', 'smtp_server_listening', { port: PORT });
});

process.on('SIGTERM', () => {
  log('info', 'graceful_shutdown');
  server.close(() => process.exit(0));
});
