<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends BaseApiController
{
    /**
     * Paginated email list for an alias.
     */
    public function index(Request $request, Alias $alias): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::EmailsRead->value), 403);
        $this->authorize('view', $alias);
        $this->checkTokenAliasAccess($request, $alias);

        $filter = $request->query('filter', 'all'); // all | unread | read

        $query = InboundEmail::where('alias_id', $alias->id)->latest();

        match ($filter) {
            'unread' => $query->unread(),
            'read'   => $query->read(),
            default  => null,
        };

        $search = $request->query('search', '');
        if ($search !== '') {
            $query->search($search);
        }

        $emails = $query->paginate(50);

        return response()->json([
            'data' => $emails->getCollection()->map(fn ($e) => $this->formatEmail($e, brief: true)),
            'meta' => [
                'current_page' => $emails->currentPage(),
                'last_page'    => $emails->lastPage(),
                'total'        => $emails->total(),
            ],
        ]);
    }

    /**
     * Get a single email with full body and mark it as read.
     */
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
     */
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
