<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\ApplicationState;

class VersionChecker
{
    public function check(bool $force = false): array
    {
        if ($force) {
            $this->clearCache();
        }

        return Cache::remember('app_version_check', config('version.cache_ttl'), function () {
            $repository = config('versioncheck.repository');
            $url = "https://api.github.com/repos/{$repository}/releases/latest";

            $request = Http::acceptJson();
            if ($token = config('versioncheck.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Unable to contact GitHub API ('.$url.')',
                    'status' => $response->status(),
                ];
            }

            $data = $response->json();
            $latest = ltrim($data['tag_name'] ?? '0.0.0', 'v');
            $current = config('emailalias.version');

            $result = [
                'success' => true,
                'current' => $current,
                'latest' => $latest,
                'up_to_date' => version_compare($current, $latest, '>='),
                'has_update' => version_compare($current, $latest, '<'),
                'release_url' => $data['html_url'] ?? null,
                'published_at' => $data['published_at'] ?? null,
                'checked_at' => now()->toISOString(),
            ];

            ApplicationState::updateOrCreate(
                ['key' => 'app_version_status'],
                ['value' => $result]
            );

            return $result;
        });
    }

    public function clearCache(): void
    {
        Cache::forget('app_version_check');
    }
}