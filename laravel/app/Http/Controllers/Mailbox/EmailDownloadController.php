<?php

namespace App\Http\Controllers\Mailbox;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an individual email as a standard RFC 2822 .eml file.
 *
 * The body is reconstructed from the stored parsed fields rather than from
 * the raw MIME message (which is not persisted), so the output is a valid but
 * re-generated EML — faithful in content, not byte-identical to the original.
 */
class EmailDownloadController extends Controller
{
    public function eml(InboundEmail $email, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorize('view', $email);

        $auditLogger->log(AuditEvent::EmailDownloaded, $email);

        $content  = $this->buildEml($email->load('alias', 'attachments'));
        $filename = Str::slug(mb_substr($email->subject ?: 'email', 0, 80)) . '.eml';

        return response()->stream(
            static function () use ($content) { echo $content; },
            200,
            [
                'Content-Type'        => 'message/rfc822',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length'      => strlen($content),
                'Cache-Control'       => 'no-store',
            ]
        );
    }

    // ── EML builder ───────────────────────────────────────────────────────────────

    private function buildEml(InboundEmail $email): string
    {
        $id          = $email->id;
        $hasHtml     = $email->body_html !== null && $email->body_html !== '';
        $hasText     = $email->body_text !== null && $email->body_text !== '';
        $attachments = $email->attachments;

        // ── Body part ─────────────────────────────────────────────────────────────

        $altBound = 'EA_ALT_' . md5($id . '_alt');

        if ($hasHtml && $hasText) {
            $bodyType    = "multipart/alternative; boundary=\"{$altBound}\"";
            $bodyContent = "--{$altBound}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . quoted_printable_encode($email->body_text)
                . "\r\n\r\n--{$altBound}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . quoted_printable_encode($email->body_html)
                . "\r\n\r\n--{$altBound}--";
        } elseif ($hasHtml) {
            $bodyType    = 'text/html; charset=UTF-8';
            $bodyContent = "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . quoted_printable_encode($email->body_html);
        } else {
            $bodyType    = 'text/plain; charset=UTF-8';
            $bodyContent = "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                . quoted_printable_encode($email->body_text ?? '');
        }

        // ── Attachment parts ──────────────────────────────────────────────────────

        $attParts = '';

        foreach ($attachments as $att) {
            $attParts .= "Content-Type: {$att->mime_type}; name=\"{$att->filename}\"\r\n"
                . "Content-Disposition: attachment; filename=\"{$att->filename}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n";

            try {
                $raw       = Storage::disk($att->disk)->get($att->path);
                $attParts .= chunk_split(base64_encode($raw), 76, "\r\n");
            } catch (\Throwable) {
                $attParts .= chunk_split(base64_encode('[attachment unavailable]'), 76, "\r\n");
            }

            $attParts .= "\r\n";
        }

        // ── Assemble ──────────────────────────────────────────────────────────────

        $from = $email->from_name
            ? '"' . str_replace('"', '\\"', $email->from_name) . '" <' . $email->from_address . '>'
            : $email->from_address;

        $baseHeaders = [
            'MIME-Version: 1.0',
            'Message-ID: <' . $id . '@emailalias>',
            'From: ' . $from,
            'To: ' . ($email->alias?->address ?? 'unknown'),
            'Subject: ' . ($email->subject ?? '(no subject)'),
            'Date: ' . $email->created_at->toRfc2822String(),
        ];

        if ($attParts !== '') {
            // multipart/mixed wraps body + attachments
            $mixedBound = 'EA_MIXED_' . md5($id . '_mixed');
            $headers    = implode("\r\n", $baseHeaders)
                . "\r\nContent-Type: multipart/mixed; boundary=\"{$mixedBound}\"";

            return $headers . "\r\n\r\n"
                . "--{$mixedBound}\r\n"
                . "Content-Type: {$bodyType}\r\n\r\n"
                . $bodyContent
                . "\r\n\r\n--{$mixedBound}\r\n"
                . $attParts
                . "--{$mixedBound}--\r\n";
        }

        // Simple case: body type goes directly in top-level headers
        $headers = implode("\r\n", $baseHeaders) . "\r\nContent-Type: {$bodyType}";

        return $headers . "\r\n\r\n" . $bodyContent . "\r\n";
    }
}
