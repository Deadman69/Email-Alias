<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

/**
 * Returns the complete list of recipient domains the SMTP receiver must accept.
 *
 * Protected by the `internal` middleware (shared SMTP_INTERNAL_SECRET).
 * Called by the SMTP server on startup and periodically to refresh its list.
 *
 * The response is the union of:
 *   1. All active domains from the `domains` table.
 *   2. Distinct domain-name strings from active aliases whose domain_id is null
 *      (i.e. the domain was deleted but the aliases were kept).
 *      This ensures orphaned aliases keep receiving mail.
 */
class DomainsController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::allNames();

        return response()->json([
            'domains'    => array_values($domains),
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
