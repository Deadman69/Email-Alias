<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TokenAbility;
use App\Models\Attachment;
use App\Models\InboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Dedoc\Scramble\Attributes\Response;

class AttachmentController extends BaseApiController
{
    /**
     * List attachments for a specific email.
     */
    #[Response(200, 'List of email attachments',
        type: 'array{
            data: array<int, array{
                id: string,
                filename: string,
                mime_type: string,
                size_bytes: int,
                download_url: string
            }>
        }'
    )]
    #[Response(403, 'Unauthorized or forbidden',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Email not found',
        type: 'array{
            message: string
        }'
    )]
    public function index(Request $request, InboundEmail $email): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AttachmentsRead->value), 403);
        $this->authorize('view', $email);

        // Token alias restriction check via the parent alias
        $alias = $email->alias()->withTrashed()->first();
        if ($alias) {
            $this->checkTokenAliasAccess($request, $alias);
        }

        return response()->json([
            'data' => $email->attachments->map(fn ($a) => $this->formatAttachment($a)),
        ]);
    }

    /**
     * Stream/download a single attachment.
     */
    #[Response(200, 'Attachment download stream')]
    #[Response(403, 'Unauthorized or forbidden',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Attachment or file not found',
        type: 'array{
            message: string
        }'
    )]
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AttachmentsRead->value), 403);

        $email = $attachment->email()->with('alias')->firstOrFail();
        $this->authorize('view', $email);

        $alias = $email->alias;
        if ($alias) {
            $this->checkTokenAliasAccess($request, $alias);
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    /** @return array<string, mixed> */
    private function formatAttachment(Attachment $attachment): array
    {
        return [
            'id'         => $attachment->id,
            'filename'   => $attachment->filename,
            'mime_type'  => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'download_url' => route('api.attachments.download', $attachment->id),
        ];
    }
}
