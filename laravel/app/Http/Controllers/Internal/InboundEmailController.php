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
        $validated = $request->validate([
            'to'                        => ['required', 'array', 'min:1', 'max:50'],
            'to.*'                      => ['required', 'string', 'email', 'max:255'],
            'from_address'              => ['required', 'string', 'email', 'max:255'],
            'from_name'                 => ['nullable', 'string', 'max:255'],
            'subject'                   => ['nullable', 'string', 'max:998'],
            // body limits: 2 MB for HTML, 500 KB for plain text (prevents DoS via giant payloads)
            'body_html'                 => ['nullable', 'string', 'max:2000000'],
            'body_text'                 => ['nullable', 'string', 'max:500000'],
            'headers'                   => ['nullable', 'array', 'max:200'],
            'headers.*'                 => ['nullable', 'string', 'max:8192'],
            'size_bytes'                => ['nullable', 'integer', 'min:0', 'max:26214400'], // 25 MB cap
            'attachments'               => ['nullable', 'array', 'max:50'],
            'attachments.*.filename'    => ['required', 'string', 'max:255'],
            'attachments.*.content_type'=> ['nullable', 'string', 'max:127'],
            'attachments.*.size_bytes'  => ['nullable', 'integer', 'min:0', 'max:5242880'], // 5 MB per attachment
            // base64 of 5 MB ≈ 6.8 MB chars; cap at 7 MB to match SMTP-level attachment limit
            'attachments.*.content_base64' => ['nullable', 'string', 'max:7000000'],
            'attachments.*.checksum'    => ['nullable', 'string', 'max:128'],
        ]);

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
