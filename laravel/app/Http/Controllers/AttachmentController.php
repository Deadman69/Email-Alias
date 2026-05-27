<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Download an attachment — enforces ownership via InboundEmailPolicy.
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        // Delegate authorization to the email's policy
        Gate::authorize('view', $attachment->email()->with('alias')->firstOrFail());

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Attachment not found.');
        }

        $stream = Storage::disk($attachment->disk)->readStream($attachment->path);
        abort_if($stream === false, 404);

        $inlineMimeTypes = [
            'application/pdf',
            'text/plain',
            'text/html',
            'application/json',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
        ];

        $disposition = in_array($attachment->mime_type, $inlineMimeTypes, true) ? 'inline' : 'attachment';
        return response()->stream(
            fn () => fpassthru($stream),
            200,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Length' => $attachment->size_bytes,
                'Content-Disposition' =>
                    $disposition .
                    '; filename="' .
                    addslashes($attachment->original_filename) .
                    '"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
