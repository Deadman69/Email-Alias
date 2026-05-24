<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Services\AliasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Response;
use App\Enums\AliasType; // used by documentation
use App\Support\PaginationMeta;

/**
 * @tags Admin
 */
class AliasController extends Controller
{
    /**
     * Paginated list of all aliases on the platform (admin view).
     * 
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 50
     * @queryParam search string Filter aliases by address.
     * @queryParam user_id integer Show aliases of a specific user.
     */
    #[Response(200, 'Paginated aliases list',
        type: 'array{
            data: array<int, array{
                id: string,
                address: string,
                type: AliasType,
                owner: array{id: int|null, email: string|null},
                expires_at: string|null,
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
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminAliases->value), 403);
        $data = $request->validate(PaginationMeta::$validationRules);

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
            'meta' => PaginationMeta::from($aliases),
        ]);
    }

    /**
     * Delete any alias (admin action).
     */
    #[Response(204, 'Alias deleted successfully')]
    #[Response(403, 'Unauthorized or missing token ability',
        type: 'array{
            message: string
        }'
    )]
    #[Response(404, 'Alias not found',
        type: 'array{
            message: string
        }'
    )]
    public function destroy(Request $request, Alias $alias, AliasService $aliasService): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($request->user()->tokenCan(TokenAbility::AdminAliases->value), 403);

        // AliasService::delete() logs AdminAliasDeleted with correct actor attribution.
        // We do NOT add a second log here to avoid double-entries in the audit trail.
        $aliasService->delete($alias, byAdmin: true, actingUser: $request->user());

        return response()->json(null, 204);
    }
}
