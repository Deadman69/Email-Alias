<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

/**
 * Returns the list of allowed recipient domains for the SMTP receiver.
 *
 * Protected by the `internal` middleware (shared SMTP_INTERNAL_SECRET).
 * Called by the SMTP server on startup and periodically to refresh its list
 * without requiring a restart.
 */
class DomainsController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::allNames();

        // Always include the legacy fallback domain from config so the SMTP
        // server keeps working even if the domains table is empty.
        $legacy = (string) config('emailalias.domain', '');
        if ($legacy && ! in_array($legacy, $domains, true)) {
            $domains[] = $legacy;
        }

        return response()->json([
            'domains'    => array_values($domains),
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
