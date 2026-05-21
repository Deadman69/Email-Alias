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

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }
}
