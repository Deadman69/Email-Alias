<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Export paginated audit logs with optional filters.
     *
     * Query params: event, user_id, from (date), to (date)
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AdminLogs->value), 403);

        // Validate event against the known enum values to prevent free-form string injection.
        $validEvents = array_column(AuditEvent::cases(), 'value');

        $request->validate([
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
            ->paginate(200);

        return response()->json([
            'data' => $logs->getCollection()->map(fn ($log) => [
                'id'         => $log->id,
                'event'      => $log->event->value,
                'user'       => $log->user ? ['id' => $log->user->id, 'email' => $log->user->email] : null,
                'ip_address' => $log->ip_address,
                'metadata'   => $log->metadata,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
