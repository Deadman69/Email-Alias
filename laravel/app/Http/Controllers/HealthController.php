<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Returns the status of all platform services.
 *
 * Accessible at GET /health (web, HTML or JSON) and GET /api/v1/health (JSON).
 * Access control is handled by the `health.access` middleware whose behaviour
 * is driven by the `health_check_visibility` platform setting.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse|\Illuminate\View\View
    {
        $checks  = $this->runChecks();
        $healthy = collect($checks)->every(fn (array $c) => $c['status'] === 'ok');

        $payload = [
            'healthy'  => $healthy,
            'status'   => $healthy ? 'healthy' : 'degraded',
            'version'  => config('emailalias.version', '0.0.0'),
            'checks'   => $checks,
            'timestamp'=> now()->toIso8601String(),
        ];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, $healthy ? 200 : 503);
        }

        return view('health', $payload);
    }

    // ── Service checks ────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{status: string, latency_ms?: float, error?: string}>
     */
    private function runChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
            'smtp receiver'     => $this->checkTcp(
                config('emailalias.health_smtp_host', 'smtp-server'),
                (int) config('emailalias.health_smtp_port', 25),
            ),
            'reverb'   => $this->checkTcp(
                config('emailalias.health_reverb_host', 'reverb'),
                (int) config('emailalias.health_reverb_port', 8080),
            ),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 1);

            return ['status' => 'ok', 'latency_ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key   = 'health_check_' . bin2hex(random_bytes(4));
            $start = microtime(true);
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $ms = round((microtime(true) - $start) * 1000, 1);

            return $value === 'ok'
                ? ['status' => 'ok', 'latency_ms' => $ms]
                : ['status' => 'error', 'error' => 'Cache read/write mismatch'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk  = Storage::disk(config('filesystems.attachment_disk', 'local'));
            $file  = 'health_check_' . bin2hex(random_bytes(4)) . '.tmp';
            $start = microtime(true);
            $disk->put($file, 'ok');
            $read = $disk->get($file);
            $disk->delete($file);
            $ms = round((microtime(true) - $start) * 1000, 1);

            return $read === 'ok'
                ? ['status' => 'ok', 'latency_ms' => $ms]
                : ['status' => 'error', 'error' => 'Storage read/write mismatch'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify a TCP service is reachable (SMTP, Reverb, etc.).
     */
    private function checkTcp(string $host, int $port): array
    {
        $start  = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, 3);
        $ms     = round((microtime(true) - $start) * 1000, 1);

        if ($socket) {
            fclose($socket);

            return ['status' => 'ok', 'latency_ms' => $ms];
        }

        return ['status' => 'error', 'error' => $this->formatSocketError($errno, $errstr)];
    }

    /**
     * Format the PHP default error to a more human readable string
     */
    private function formatSocketError(int $errno, string $errstr): string
    {
        $message = strtolower($errstr);
        $friendly = match (true) {
            str_contains($message, 'getaddrinfo') => __('Unknown host'),
            str_contains($message, 'connection refused') => __('Connection refused'),
            str_contains($message, 'timed out') => __('Connection timed out'),
            str_contains($message, 'no route to host') => __('No route to host'),

            default => __('Service unreachable'),
        };

        if (config('app.debug')) {
            return "{$friendly} — {$errstr} ({$errno})";
        }

        return $friendly;
    }
}
