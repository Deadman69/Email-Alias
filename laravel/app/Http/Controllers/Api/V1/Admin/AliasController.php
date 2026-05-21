<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Services\AliasService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AliasController extends Controller
{
    /**
     * Paginated list of all aliases on the platform (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AdminAliases->value), 403);

        $query = Alias::with('user')
            ->when($request->query('search'), fn ($q, $s) => $q->where('address', 'like', "%{$s}%"))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->latest();

        $aliases = $query->paginate(100);

        return response()->json([
            'data' => $aliases->getCollection()->map(fn ($a) => [
                'id'         => $a->id,
                'address'    => $a->address,
                'type'       => $a->type->value,
                'owner'      => ['id' => $a->user?->id, 'email' => $a->user?->email],
                'expires_at' => $a->expires_at?->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $aliases->currentPage(),
                'last_page'    => $aliases->lastPage(),
                'total'        => $aliases->total(),
            ],
        ]);
    }

    /**
     * Delete any alias (admin action).
     */
    public function destroy(Request $request, Alias $alias, AliasService $aliasService, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AdminAliases->value), 403);

        $aliasService->delete($alias, byAdmin: true);
        $auditLogger->log(AuditEvent::AdminAliasDeleted, null, [
            'address'  => $alias->address,
            'owner_id' => $alias->user_id,
            'via'      => 'api',
        ]);

        return response()->json(null, 204);
    }
}
