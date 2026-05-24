<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

/**
 * Public (app-token–protected) endpoint that returns configured domains.
 *
 * Protected by the `app.token:read:domains` middleware.
 * Intended for server-to-server consumers (e.g. the SMTP receiver).
 *
 * GET /api/v1/domains
 */
class DomainsController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::orderByDesc('is_primary')->orderBy('name')->get(['name', 'is_primary']);

        $legacy = (string) config('emailalias.domain', '');

        return response()->json([
            'domains'    => $domains->map(fn ($d) => [
                'name'       => $d->name,
                'is_primary' => (bool) $d->is_primary,
            ])->values(),
            'legacy_domain' => $legacy ?: null,
            'updated_at'    => now()->toIso8601String(),
        ]);
    }
}
