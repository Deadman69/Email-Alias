'use strict';

import { SMTPServer } from 'smtp-server';
import { simpleParser } from 'mailparser';
import fetch from 'node-fetch';
import { createServer as createHttpServer } from 'http';

// ── Config ────────────────────────────────────────────────────────────────────

const LARAVEL_INBOUND_URL  = process.env.LARAVEL_INBOUND_URL  || 'http://app:9000/internal/inbound';
const SMTP_INTERNAL_SECRET = process.env.SMTP_INTERNAL_SECRET || '';
const PORT                 = parseInt(process.env.SMTP_PORT   || '25', 10);
const HEALTH_PORT          = parseInt(process.env.HEALTH_PORT || '8025', 10);
const MAX_CLIENTS          = parseInt(process.env.MAX_CLIENTS || '100', 10);
const MAX_MESSAGE_SIZE     = 25 * 1024 * 1024;  // 25 MB SMTP-level cap
const MAX_ATTACHMENT_SIZE  =  5 * 1024 * 1024;  // 5 MB per attachment in payload

// Allowed recipient domains — comma-separated env var.
// When set, any RCPT TO pointing to a different domain is rejected immediately.
// Leave empty to accept all domains (not recommended in production).
const ALLOWED_DOMAINS = (process.env.SMTP_ALLOWED_DOMAIN || '')
  .split(',')
  .map(d => d.trim().toLowerCase())
  .filter(Boolean);

// ── Startup validation ────────────────────────────────────────────────────────

if (!SMTP_INTERNAL_SECRET && process.env.NODE_ENV === 'production') {
  process.stderr.write(JSON.stringify({
    level: 'error',
    ts: new Date().toISOString(),
    message: 'SMTP_INTERNAL_SECRET is not set. All forwards to Laravel will be rejected with 403. Aborting.',
  }) + '\n');
  process.exit(1);
}

if (ALLOWED_DOMAINS.length === 0) {
  process.stderr.write(JSON.stringify({
    level: 'warn',
    ts: new Date().toISOString(),
    message: 'SMTP_ALLOWED_DOMAIN is not set — accepting RCPT TO for any domain (open relay).',
  }) + '\n');
}

// ── Logging ───────────────────────────────────────────────────────────────────

function log(level, message, extra = {}) {
  process.stdout.write(JSON.stringify({ level, ts: new Date().toISOString(), message, ...extra }) + '\n');
}

// ── Metrics counters ──────────────────────────────────────────────────────────

const counters = {
  emails_received_total:  0,
  emails_forwarded_total: 0,
  emails_failed_total:    0,
  rcpt_rejected_total:    0,
};

// ── Attachment serializer ─────────────────────────────────────────────────────

/**
 * Serialize attachments for the Laravel payload.
 * Files exceeding MAX_ATTACHMENT_SIZE are included as metadata-only
 * (content_base64: null) so Laravel still records their existence.
 */
function serializeAttachments(attachments = []) {
  return attachments.map(att => {
    const sizeBytes = att.size ?? att.content?.length ?? 0;
    const tooLarge  = sizeBytes > MAX_ATTACHMENT_SIZE;

    return {
      filename:       att.filename ?? 'attachment',
      content_type:   att.contentType ?? 'application/octet-stream',
      size_bytes:     sizeBytes,
      content_base64: tooLarge ? null : (att.content ? att.content.toString('base64') : null),
      checksum:       att.checksum ?? null,
      skipped:        tooLarge,
    };
  });
}

// ── Laravel forwarder ─────────────────────────────────────────────────────────

/**
 * Forward a parsed email to Laravel with up to 3 attempts and exponential backoff.
 * On permanent failure the email is dropped — the SMTP client has already been
 * told "250 OK" so there is nothing to bounce back.
 */
async function forwardToLaravel(parsed, rawBuffer, recipients) {
  const attachments = serializeAttachments(parsed.attachments ?? []);

  const payload = {
    from:         parsed.from?.text ?? '',
    from_address: parsed.from?.value?.[0]?.address ?? '',
    from_name:    parsed.from?.value?.[0]?.name ?? '',
    to:           recipients,
    subject:      parsed.subject ?? '(no subject)',
    body_html:    parsed.html  || null,
    body_text:    parsed.text  || null,
    headers:      Object.fromEntries(
      [...(parsed.headers?.entries?.() ?? [])].map(([k, v]) => [k, String(v)])
    ),
    size_bytes:   rawBuffer.length,
    attachments,
  };

  const attempts = 3;
  for (let i = 1; i <= attempts; i++) {
    try {
      const res = await fetch(LARAVEL_INBOUND_URL, {
        method:  'POST',
        headers: {
          'Content-Type':  'application/json',
          'X-SMTP-Secret': SMTP_INTERNAL_SECRET,
        },
        body:   JSON.stringify(payload),
        signal: AbortSignal.timeout(15_000),
      });

      if (res.ok) {
        counters.emails_forwarded_total++;
        log('info', 'forwarded', {
          to:          recipients,
          subject:     payload.subject,
          size:        payload.size_bytes,
          attachments: attachments.length,
          attempt:     i,
        });
        return;
      }

      log('warn', 'laravel_non_ok', { status: res.status, attempt: i });
    } catch (err) {
      log('warn', 'forward_error', { err: err.message, attempt: i });
    }

    if (i < attempts) {
      await new Promise(r => setTimeout(r, 1000 * i)); // 1 s, 2 s backoff
    }
  }

  counters.emails_failed_total++;
  log('error', 'forward_failed_all_attempts', { to: recipients });
}

// ── SMTP Server ───────────────────────────────────────────────────────────────

const server = new SMTPServer({
  authOptional:     true,
  disabledCommands: ['AUTH'],
  maxMessageSize:   MAX_MESSAGE_SIZE,
  maxClients:       MAX_CLIENTS,

  onRcptTo(address, session, callback) {
    if (ALLOWED_DOMAINS.length > 0) {
      const domain = address.address.split('@')[1]?.toLowerCase();

      if (!domain || !ALLOWED_DOMAINS.includes(domain)) {
        counters.rcpt_rejected_total++;
        log('warn', 'rcpt_rejected', {
          address:  address.address,
          domain,
          allowed:  ALLOWED_DOMAINS,
          remoteIp: session.remoteAddress,
        });
        return callback(new Error('Recipient address rejected'));
      }
    }

    log('debug', 'rcpt_to', { address: address.address });
    callback();
  },

  onData(stream, session, callback) {
    const chunks = [];

    stream.on('data',  chunk => chunks.push(chunk));

    stream.on('end', async () => {
      const rawBuffer  = Buffer.concat(chunks);
      const recipients = session.envelope.rcptTo.map(r => r.address);

      counters.emails_received_total++;

      try {
        const parsed = await simpleParser(rawBuffer);
        await forwardToLaravel(parsed, rawBuffer, recipients);
      } catch (err) {
        // Accept the message anyway — never bounce due to our own parse/forward error.
        log('error', 'parse_or_forward_error', { err: err.message });
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
  log('info', 'smtp_server_listening', {
    port:           PORT,
    maxClients:     MAX_CLIENTS,
    allowedDomains: ALLOWED_DOMAINS.length > 0 ? ALLOWED_DOMAINS : '*',
  });
});

// ── Health-check + metrics HTTP endpoint ─────────────────────────────────────

const healthServer = createHttpServer((req, res) => {
  const url = req.url?.split('?')[0];

  if (url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', smtpPort: PORT }));
    return;
  }

  if (url === '/metrics') {
    // Prometheus text format (exposition format 0.0.4)
    const lines = Object.entries(counters)
      .map(([k, v]) => `# TYPE ${k} counter\n${k} ${v}`)
      .join('\n');
    res.writeHead(200, { 'Content-Type': 'text/plain; version=0.0.4; charset=utf-8' });
    res.end(lines + '\n');
    return;
  }

  res.writeHead(404);
  res.end();
});

healthServer.listen(HEALTH_PORT, '0.0.0.0', () => {
  log('info', 'health_server_listening', { port: HEALTH_PORT });
});

// ── Graceful shutdown ─────────────────────────────────────────────────────────

process.on('SIGTERM', () => {
  log('info', 'graceful_shutdown_start');

  // Force-exit after 30 s so a stuck connection can never block the shutdown.
  const forceExit = setTimeout(() => {
    log('warn', 'forced_shutdown_after_timeout');
    process.exit(1);
  }, 30_000);
  forceExit.unref(); // Don't let this timer keep the event loop alive.

  // Stop accepting new SMTP connections and wait for current ones to finish.
  server.close(() => {
    log('info', 'smtp_server_closed');

    healthServer.close(() => {
      log('info', 'health_server_closed');
      clearTimeout(forceExit);
      process.exit(0);
    });
  });
});
