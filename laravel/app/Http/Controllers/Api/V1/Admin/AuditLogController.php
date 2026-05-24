<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Response;
use App\Support\PaginationMeta;

/**
 * @tags Admin
 */
class AuditLogController extends Controller
{
    /**
     * Export paginated audit logs with optional filters.
     *
     * @queryParam event AuditEvent Filter by audit event.
     * @queryParam user_id integer Filter by user ID.
     * @queryParam from string Filter logs created after this date.
     * @queryParam to string Filter logs created before this date.
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 1000). Example: 100
     */
    #[Response(200, 'Paginated audit logs export',
        type: 'array{
            data: array<int, array{
                id: string,
                event: AuditEvent,
                user: array{id: int, email: string}|null,
                ip_address: string|null,
                metadata: array<string, mixed>|null,
                created_at: string
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
    #[Response(403, 'Unauthorized or missing token ability',
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
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AdminLogs->value), 403);

        // Validate event against the known enum values to prevent free-form string injection.
        $validEvents = array_column(AuditEvent::cases(), 'value');

        $request->validate([
            ...PaginationMeta::$validationRules,
            'from'    => 'nullable|date',
            'to'      => 'nullable|date',
            'event'   => ['nullable', 'string', \Illuminate\Validation\Rule::in($validEvents)],
            'user_id' => 'nullable|integer',
        ]);

        $logs = AuditLog::with('user')
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate($data['per_page'] ?? 200)
            ->withQueryString();

        return response()->json([
            'data' => $logs->getCollection()->map(fn ($log) => [
                'id'         => $log->id,
                'event'      => $log->event->value,
                'user'       => $log->user ? ['id' => $log->user->id, 'email' => $log->user->email] : null,
                'ip_address' => $log->ip_address,
                'metadata'   => $log->metadata,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'meta' => PaginationMeta::from($logs),
        ]);
    }
}
