<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Response;
use App\Support\PaginationMeta;

class EmailController extends BaseApiController
{
    /**
     * Paginated email list for an alias.
     *
     * @pathParam alias string Alias ID.
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 1000). Example: 50
     * @queryParam filter string Filter emails by status. Allowed values: all, unread, read.
     * @queryParam search string Search emails by sender, subject or content.
     */
    #[Response(200, 'Paginated emails list',
        type: 'array{
            data: array<int, array{
                id: string,
                from: array{
                    address: string,
                    name: string|null
                },
                subject: string|null,
                read_at: string|null,
                size_bytes: int,
                is_truncated: bool,
                received_at: string
            }>,
            meta: array{
                current_page: int,
                last_page: int,
                per_page: int,
                total: int,
                count: int,
                from: int|null,
                to: int|null,
                has_more_pages: bool
            }
        }'
    )]
    #[Response(403, 'Unauthorized or forbidden',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Alias not found',
        type: 'array{
            message: string
        }'
    )]
    #[Response(422, 'Validation error',
        type: 'array{
            message: string,
            errors: array<string, array<int, string>>
        }'
    )]
    public function index(Request $request, Alias $alias): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::EmailsRead->value), 403);
        $this->authorize('view', $alias);
        $this->checkTokenAliasAccess($request, $alias);

        $data = $request->validate([
            ...PaginationMeta::$validationRules,
            'filter' => 'nullable|in:all,unread,read',
            'search' => 'nullable|string|max:255',
        ]);

        $filter = $data['filter'] ?? 'all';

        $query = InboundEmail::where('alias_id', $alias->id)->latest();

        match ($filter) {
            'unread' => $query->unread(),
            'read'   => $query->read(),
            default  => null,
        };

        $search = $data['search'] ?? '';
        if ($search !== '') {
            $query->search($search);
        }

        $emails = $query
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return response()->json([
            'data' => $emails->getCollection()->map(fn ($e) => $this->formatEmail($e, brief: true)),
            'meta' => PaginationMeta::from($emails),
        ]);
    }

    /**
     * Get a single email with full body and mark it as read.
     *
     * @pathParam alias string Alias ID.
     * @pathParam email string Email ID.
     */
    #[Response(200, 'Email details',
        type: 'array{
            data: array{
                id: string,
                from: array{
                    address: string,
                    name: string|null
                },
                subject: string|null,
                read_at: string|null,
                size_bytes: int,
                is_truncated: bool,
                received_at: string,
                body_text: string|null,
                body_html: string|null,
                headers: array<string, mixed>|null,
                attachments: array<int, array{
                    id: string,
                    filename: string,
                    mime_type: string,
                    size_bytes: int
                }>
            }
        }'
    )]
    #[Response(403, 'Unauthorized or forbidden',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Alias or email not found',
        type: 'array{
            message: string
        }'
    )]
    public function show(Request $request, Alias $alias, InboundEmail $email, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::EmailsRead->value), 403);
        $this->authorize('view', $alias);
        $this->checkTokenAliasAccess($request, $alias);
        $this->authorize('view', $email);

        $email->markAsRead();
        $auditLogger->log(AuditEvent::ApiEmailRead, $email);

        return response()->json(['data' => $this->formatEmail($email->load('attachments'), brief: false)]);
    }

    /**
     * Delete an email. Owner only.
     * 
     * @pathParam alias string Alias ID.
     * @pathParam email string Email ID.
     */
    #[Response(204, 'Email deleted successfully')]
    #[Response(403, 'Unauthorized or forbidden',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Alias or email not found',
        type: 'array{
            message: string
        }'
    )]
    public function destroy(Request $request, Alias $alias, InboundEmail $email, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::EmailsDelete->value), 403);
        $this->authorize('view', $alias);
        $this->checkTokenAliasAccess($request, $alias);
        $this->authorize('delete', $email);

        $auditLogger->log(AuditEvent::ApiEmailDeleted, $email, [
            'subject' => $email->subject,
            'from'    => $email->from_address,
        ]);

        $email->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  bool  $brief  When true, omit body content (for list views)
     * @return array<string, mixed>
     */
    private function formatEmail(InboundEmail $email, bool $brief): array
    {
        $data = [
            'id'          => $email->id,
            'from'        => ['address' => $email->from_address, 'name' => $email->from_name],
            'subject'     => $email->subject,
            'read_at'     => $email->read_at?->toIso8601String(),
            'size_bytes'  => $email->size_bytes,
            'is_truncated' => $email->is_truncated,
            'received_at' => $email->created_at->toIso8601String(),
        ];

        if (! $brief) {
            $data['body_text'] = $email->body_text;
            $data['body_html'] = $email->body_html;
            $data['headers']   = $email->headers;
            $data['attachments'] = $email->relationLoaded('attachments')
                ? $email->attachments->map(fn ($a) => [
                    'id'         => $a->id,
                    'filename'   => $a->filename,
                    'mime_type'  => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                ])->toArray()
                : [];
        }

        return $data;
    }
}
