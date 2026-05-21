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
            'body_html'                 => ['nullable', 'string'],
            'body_text'                 => ['nullable', 'string'],
            'headers'                   => ['nullable', 'array'],
            'size_bytes'                => ['nullable', 'integer', 'min:0'],
            'attachments'               => ['nullable', 'array', 'max:50'],
            'attachments.*.filename'    => ['required', 'string', 'max:255'],
            'attachments.*.content_type'=> ['nullable', 'string', 'max:127'],
            'attachments.*.size_bytes'  => ['nullable', 'integer', 'min:0'],
            'attachments.*.content_base64' => ['nullable', 'string'],
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
