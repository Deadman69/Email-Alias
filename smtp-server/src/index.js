'use strict';

import { SMTPServer } from 'smtp-server';
import { simpleParser } from 'mailparser';
import fetch from 'node-fetch';
import { createServer as createHttpServer } from 'http';

// ── Config ────────────────────────────────────────────────────────────────────

const LARAVEL_INBOUND_URL  = process.env.LARAVEL_INBOUND_URL  || 'http://nginx/internal/inbound';
const LARAVEL_DOMAINS_URL  = process.env.LARAVEL_DOMAINS_URL  || 'http://nginx/internal/domains';
const SMTP_INTERNAL_SECRET = process.env.SMTP_INTERNAL_SECRET || '';
const PORT                 = parseInt(process.env.SMTP_PORT   || '2525', 10);
const HEALTH_PORT          = parseInt(process.env.HEALTH_PORT || '8025', 10);
const MAX_CLIENTS          = parseInt(process.env.MAX_CLIENTS || '100', 10);
const MAX_MESSAGE_SIZE     = 25 * 1024 * 1024;  // 25 MB SMTP-level cap
const MAX_ATTACHMENT_SIZE  =  5 * 1024 * 1024;  // 5 MB per attachment in payload
const MAX_RCPT_PER_MESSAGE = 50;                 // anti-amplification: reject excess RCPT TO

// Domain refresh interval in ms (default: 5 minutes).
const DOMAIN_REFRESH_MS = parseInt(process.env.DOMAIN_REFRESH_MS || String(5 * 60 * 1000), 10);

// IMPORTANT: there is intentionally NO SMTP_ALLOWED_DOMAIN env-var fallback.
// Domains are exclusively managed through the platform's Settings → Domains UI
// and fetched from Laravel via /internal/domains on startup + every DOMAIN_REFRESH_MS.
// An empty allowed-domain set means "no domains configured yet" → reject all RCPT TO.

// ── Startup validation ────────────────────────────────────────────────────────

if (!SMTP_INTERNAL_SECRET && process.env.NODE_ENV === 'production') {
  process.stderr.write(JSON.stringify({
    level: 'error',
    ts: new Date().toISOString(),
    message: 'SMTP_INTERNAL_SECRET is not set. All forwards to Laravel will be rejected with 403. Aborting.',
  }) + '\n');
  process.exit(1);
}

// ── Logging ───────────────────────────────────────────────────────────────────

function log(level, message, extra = {}) {
  process.stdout.write(JSON.stringify({ level, ts: new Date().toISOString(), message, ...extra }) + '\n');
}

// ── Global error handlers ─────────────────────────────────────────────────────

// Catch promise rejections that escaped their try/catch. Log and continue —
// the rejection is likely isolated and the server can keep running.
process.on('unhandledRejection', (reason) => {
  log('error', 'unhandled_rejection', { err: String(reason?.message ?? reason) });
});

// Catch synchronous exceptions that escaped all handlers. The process is in an
// unknown state so we exit immediately and let the container restart policy take over.
process.on('uncaughtException', (err) => {
  log('error', 'uncaught_exception', { err: err.message, stack: err.stack });
  process.exit(1);
});

// ── Metrics counters ──────────────────────────────────────────────────────────

const counters = {
  emails_received_total:  0,
  emails_forwarded_total: 0,
  emails_failed_total:    0,
  rcpt_rejected_total:    0,
  domain_refresh_total:   0,
  domain_refresh_failed:  0,
};

// ── Domain management ─────────────────────────────────────────────────────────

/**
 * Mutable set of allowed recipient domains.
 *
 * Populated exclusively from the Laravel /internal/domains API on startup
 * and refreshed every DOMAIN_REFRESH_MS milliseconds.
 *
 * When the set is empty (no domains configured or refresh not yet complete)
 * ALL RCPT TO commands are rejected — this is intentional fail-closed behavior.
 * There is no open-relay fallback.
 */
let allowedDomains = new Set();

/**
 * Fetch the current domain list from Laravel and update allowedDomains.
 * Falls back silently to the current in-memory list on error.
 */
async function refreshDomains() {
  try {
    const res = await fetch(LARAVEL_DOMAINS_URL, {
      headers: { 'X-SMTP-Secret': SMTP_INTERNAL_SECRET },
      signal:  AbortSignal.timeout(10_000),
    });

    if (!res.ok) {
      log('warn', 'domain_refresh_non_ok', { status: res.status });
      counters.domain_refresh_failed++;
      return;
    }

    const data = await res.json();
    const domains = (data.domains || []).map(d => String(d).toLowerCase().trim()).filter(Boolean);

    if (domains.length > 0) {
      allowedDomains = new Set(domains);
      counters.domain_refresh_total++;
      log('info', 'domain_refresh_ok', { domains });
    } else {
      log('warn', 'domain_refresh_empty', { message: 'Laravel returned an empty domain list — keeping current list.' });
    }
  } catch (err) {
    counters.domain_refresh_failed++;
    log('warn', 'domain_refresh_error', { err: err.message });
  }
}

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
 *
 * Returns true on success, false on permanent failure (all attempts exhausted).
 * The caller is responsible for translating false into a 451 SMTP temp-fail so
 * the sending MTA retries delivery rather than silently losing the message.
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
          size:        payload.size_bytes,
          attachments: attachments.length,
          attempt:     i,
        });
        return true;
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
  return false;
}

// ── SMTP Server ───────────────────────────────────────────────────────────────

const server = new SMTPServer({
  authOptional:     true,
  disabledCommands: ['AUTH'],
  maxMessageSize:   MAX_MESSAGE_SIZE,
  maxClients:       MAX_CLIENTS,
  socketTimeout:    60_000,  // close idle/slow-loris connections after 60 s
  closeTimeout:     30_000,  // grace period for in-flight connections on shutdown

  onRcptTo(address, session, callback) {
    // Reject when the per-message recipient limit is reached (anti-amplification).
    if (session.envelope.rcptTo.length >= MAX_RCPT_PER_MESSAGE) {
      counters.rcpt_rejected_total++;
      log('warn', 'rcpt_limit_reached', {
        address:  address.address,
        count:    session.envelope.rcptTo.length,
        remoteIp: session.remoteAddress,
      });
      const err = new Error('Too many recipients');
      err.responseCode = 452;
      return callback(err);
    }

    const domain = address.address.split('@')[1]?.toLowerCase();

    if (!domain || allowedDomains.size === 0 || !allowedDomains.has(domain)) {
      counters.rcpt_rejected_total++;
      log('warn', 'rcpt_rejected', {
        address:  address.address,
        domain,
        reason:   allowedDomains.size === 0 ? 'no_domains_configured' : 'domain_not_allowed',
        remoteIp: session.remoteAddress,
      });
      return callback(new Error('Recipient address rejected'));
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

      // Parse failure: the email is malformed and cannot be retried — accept with 250
      // so the sender is not notified (avoids backscatter on spam/malformed messages).
      let parsed;
      try {
        parsed = await simpleParser(rawBuffer);
      } catch (err) {
        log('error', 'parse_error', { err: err.message });
        return callback();
      }

      // Forward failure: Laravel is temporarily unreachable — return a 451 temp-fail
      // so the sending MTA queues the message and retries instead of silently losing it.
      const ok = await forwardToLaravel(parsed, rawBuffer, recipients);
      if (!ok) {
        const err = new Error('Temporary local error, please retry later');
        err.responseCode = 451;
        return callback(err);
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

// ── Startup + periodic domain refresh ────────────────────────────────────────

server.listen(PORT, '0.0.0.0', async () => {
  log('info', 'smtp_server_listening', {
    port:           PORT,
    maxClients:     MAX_CLIENTS,
    allowedDomains: allowedDomains.size > 0 ? [...allowedDomains] : '*',
  });

  // Fetch domains from Laravel on startup.
  // A short delay lets Laravel finish booting in slow environments.
  setTimeout(async () => {
    log('info', 'domain_refresh_startup');
    await refreshDomains();

    if (allowedDomains.size === 0) {
      log('warn', 'no_allowed_domains',
        { message: 'No domains configured — all RCPT TO will be rejected until a domain is added via Settings → Domains.' });
    }
  }, 3_000);

  // Refresh every DOMAIN_REFRESH_MS (default 5 min) without restart.
  setInterval(refreshDomains, DOMAIN_REFRESH_MS);
});

// ── Health-check + metrics HTTP endpoint ─────────────────────────────────────

const healthServer = createHttpServer((req, res) => {
  const url = req.url?.split('?')[0];

  if (url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status:         'ok',
      smtpPort:       PORT,
      allowedDomains: allowedDomains.size > 0 ? [...allowedDomains] : null,
    }));
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
