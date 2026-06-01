<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    /**
     * Receive an inbound email from the SMTP receiver service.
     * Protected by EnsureInternalRequest middleware.
     */
    public function store(Request $request): JsonResponse
    {
        // Derive byte limits from platform settings (set by super-admin, defaults in config).
        $maxEmailBytes      = (int) config('emailalias.max_email_size_bytes', 26_214_400);      // default 25 MB
        $maxAttachmentBytes = (int) config('emailalias.max_attachment_size_bytes', 5_242_880);   // default 5 MB
        // base64 overhead is ~4/3; add 40 % headroom so a valid attachment is never rejected
        $maxBase64Chars     = (int) ceil($maxAttachmentBytes * 1.4);
        // Body sub-limits: HTML 20 % of email cap, plain text 5 %. Both capped at sane max.
        $maxHtmlBytes  = min((int) ceil($maxEmailBytes * 0.20), 20_000_000);
        $maxTextBytes  = min((int) ceil($maxEmailBytes * 0.05),  5_000_000);

        $validated = $request->validate([
            'to'                           => ['required', 'array', 'min:1', 'max:50'],
            'to.*'                         => ['required', 'string', 'email', 'max:255'],
            'from_address'                 => ['required', 'string', 'email', 'max:255'],
            'from_name'                    => ['nullable', 'string', 'max:255'],
            'subject'                      => ['nullable', 'string', 'max:998'],
            'body_html'                    => ['nullable', 'string', 'max:' . $maxHtmlBytes],
            'body_text'                    => ['nullable', 'string', 'max:' . $maxTextBytes],
            'headers'                      => ['nullable', 'array', 'max:200'],
            'headers.*'                    => ['nullable', 'string', 'max:8192'],
            'size_bytes'                   => ['nullable', 'integer', 'min:0', 'max:' . $maxEmailBytes],
            'attachments'                  => ['nullable', 'array', 'max:50'],
            'attachments.*.filename'       => ['required', 'string', 'max:255'],
            'attachments.*.content_type'   => ['nullable', 'string', 'max:127'],
            'attachments.*.size_bytes'     => ['nullable', 'integer', 'min:0', 'max:' . $maxAttachmentBytes],
            'attachments.*.content_base64' => ['nullable', 'string', 'max:' . $maxBase64Chars],
            'attachments.*.checksum'       => ['nullable', 'string', 'max:128'],
        ]);

        // Normalize recipient addresses to lowercase so alias lookups are case-insensitive.
        // Aliases are always stored in lowercase; SMTP senders may use any case.
        $validated['to'] = array_map('mb_strtolower', $validated['to']);

        ProcessInboundEmail::dispatch(
            recipients:   $validated['to'],
            fromAddress:  $validated['from_address'],
            fromName:     $validated['from_name'] ?? null,
            subject:      $validated['subject'] ?? '(no subject)',
            bodyHtml:     $validated['body_html'] ?? null,
            bodyText:     $validated['body_text'] ?? null,
            headers:      $validated['headers'] ?? [],
            sizeBytes:    $validated['size_bytes'] ?? 0,
            attachments:  $validated['attachments'] ?? [],
        );

        return response()->json(['accepted' => true], 202);
    }
}
