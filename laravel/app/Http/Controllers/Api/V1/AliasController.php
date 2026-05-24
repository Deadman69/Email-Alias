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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Enum;
use Dedoc\Scramble\Attributes\Response;
use App\Support\PaginationMeta;

class AliasController extends BaseApiController
{
    /**
     * List the authenticated user's aliases (own + shared).
     * 
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 50
     */
    #[Response(200, 'Paginated aliases list',
        type: 'array{
            data: array<int, array{
                id: string,
                address: string,
                type: AliasType,
                label: string|null,
                expires_at: string|null,
                webhook_url: string|null,
                is_owner: bool,
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
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesRead->value), 403);
        $data = $request->validate(PaginationMeta::$validationRules);

        $userId = $request->user()->id;
        $query = Alias::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereHas('shares', fn ($q2) => $q2->where('user_id', $userId));
        });

        // Apply token alias restriction BEFORE pagination
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \App\Models\PersonalAccessToken && $token->restricted_alias_ids !== null) {
            $query->whereIn('id', $token->restricted_alias_ids);
        }

        $aliases = $query
            ->active()
            ->latest()
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return response()->json([
            'data' => $aliases->getCollection()->map(
                fn ($a) => $this->formatAlias($a)
            ),
            'meta' => PaginationMeta::from($aliases),
        ]);
    }

    /**
     * Create a new alias.
     *
     * @bodyParam type AliasType Alias type. Example: permanent
     * @bodyParam local_part string Custom local part. Must contain only letters, numbers, dots, dashes and underscores.
     * @bodyParam duration AliasDuration Alias duration. Example: 24h
     * @bodyParam label string Optional alias label.
     *
     * @throws ValidationException
     */
    #[Response(201, 'Alias created successfully',
        type: 'array{
            data: array{
                id: string,
                address: string,
                type: AliasType,
                label: string|null,
                expires_at: string|null,
                webhook_url: string|null,
                is_owner: bool,
                created_at: string
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
    public function store(Request $request, AliasService $aliasService, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->tokenCan(TokenAbility::AliasesCreate->value), 403);

        $data = $request->validate([
            'type' => ['required', 'string', new Enum(AliasType::class)],
            'local_part' => ['nullable', 'string', 'min:3', 'max:64', 'regex:/^[a-z0-9\-_\.]+$/i'],
            'duration' => ['nullable', Rule::in(array_keys(AliasType::durationsOptions()))],
            'label' => 'nullable|string|max:255',
        ]);

        $alias = $aliasService->create(
            user:      $request->user(),
            type:      $data['type']->value,
            localPart: $data['local_part'] ?? null,
            duration:  $data['duration']?->value,
            label:     $data['label'] ?? null,
        );

        $auditLogger->log(AuditEvent::ApiAliasCreated, $alias, ['address' => $alias->address]);

        return response()->json(['data' => $this->formatAlias($alias)], 201);
    }

    /**
     * Get a single alias.
     */
    #[Response(200, 'Alias details',
        type: 'array{
            data: array{
                id: string,
                address: string,
                type: AliasType,
                label: string|null,
                expires_at: string|null,
                webhook_url: string|null,
                is_owner: bool,
                created_at: string
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
    #[Response(204, 'Alias deleted successfully')]
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
