<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Response;

/**
 * Returns configured domains. Accessible with any authenticated Sanctum user
 * token, or an AppToken with the `read:domains` ability.
 * Intended for server-to-server consumers (e.g. the SMTP receiver).
 *
 * GET /api/v1/domains
 */
class DomainsController extends Controller
{
    /**
     * List all available domains.
     */
    #[Response(200, 'Configured domains list',
        type: 'array{
            domains: array<int, array{
                name: string,
                is_primary: bool
            }>,
            updated_at: string
        }'
    )]
    #[Response(403, 'Unauthorized or missing app token ability',
        type: 'array{
            message: string
        }'
    )]
    public function index(Request $request): JsonResponse
    {
        if ($appToken = $request->attributes->get('app_token')) {
            abort_unless($appToken->can('read:domains'), 403, 'Token missing required ability: read:domains.');
        }

        $domains = Domain::orderByDesc('is_primary')->orderBy('name')->get(['name', 'is_primary']);

        return response()->json([
            'domains'    => $domains->map(fn ($d) => [
                'name'       => $d->name,
                'is_primary' => (bool) $d->is_primary,
            ])->values(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
