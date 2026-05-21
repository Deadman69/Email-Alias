<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Services\AliasService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AliasController extends BaseApiController
{
    /**
     * List the authenticated user's aliases (own + shared).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesRead->value), 403);

        $userId = $request->user()->id;

        $aliases = Alias::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('shares', fn ($q2) => $q2->where('user_id', $userId));
        })
            ->active()
            ->latest()
            ->paginate(50);

        // Filter by token alias restriction
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \App\Models\PersonalAccessToken && $token->restricted_alias_ids !== null) {
            $allowed = $token->restricted_alias_ids;
            $aliases->setCollection(
                $aliases->getCollection()->filter(fn ($a) => in_array($a->id, $allowed, true))->values()
            );
        }

        return response()->json([
            'data' => $aliases->getCollection()->map(fn ($a) => $this->formatAlias($a)),
            'meta' => [
                'current_page' => $aliases->currentPage(),
                'last_page'    => $aliases->lastPage(),
                'total'        => $aliases->total(),
            ],
        ]);
    }

    /**
     * Create a new alias.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AliasService $aliasService, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesCreate->value), 403);

        $data = $request->validate([
            'type'       => 'required|in:session,duration,permanent',
            'local_part' => 'nullable|string|min:3|max:64|regex:/^[a-z0-9\-_\.]+$/i',
            'duration'   => 'nullable|in:1h,12h,24h,7d,30d',
            'label'      => 'nullable|string|max:255',
        ]);

        $alias = $aliasService->create(
            user:      $request->user(),
            type:      AliasType::from($data['type']),
            localPart: $data['local_part'] ?? null,
            duration:  $data['duration'] ?? null,
            label:     $data['label'] ?? null,
        );

        $auditLogger->log(AuditEvent::ApiAliasCreated, $alias, ['address' => $alias->address]);

        return response()->json(['data' => $this->formatAlias($alias)], 201);
    }

    /**
     * Get a single alias.
     */
    public function show(Request $request, Alias $alias): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesRead->value), 403);
        $this->authorize('view', $alias);
        $this->checkTokenAliasAccess($request, $alias);

        return response()->json(['data' => $this->formatAlias($alias->load('shares.user'))]);
    }

    /**
     * Delete an alias. Owner only.
     */
    public function destroy(Request $request, Alias $alias, AliasService $aliasService, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesDelete->value), 403);
        $this->authorize('delete', $alias);
        $this->checkTokenAliasAccess($request, $alias);

        $aliasService->delete($alias, actingUser: $request->user());
        $auditLogger->log(AuditEvent::ApiAliasDeleted, null, ['address' => $alias->address]);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function formatAlias(Alias $alias): array
    {
        return [
            'id'          => $alias->id,
            'address'     => $alias->address,
            'type'        => $alias->type->value,
            'label'       => $alias->label,
            'expires_at'  => $alias->expires_at?->toIso8601String(),
            'webhook_url' => $alias->webhook_url,
            'is_owner'    => $alias->user_id === request()->user()?->id,
            'created_at'  => $alias->created_at->toIso8601String(),
        ];
    }
}
