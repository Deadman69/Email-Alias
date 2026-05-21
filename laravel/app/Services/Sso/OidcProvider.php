<?php

namespace App\Services\Sso;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Generic OIDC provider for Laravel Socialite.
 *
 * Autodiscovers auth / token / userinfo endpoints from the OIDC discovery
 * document at {issuer}/.well-known/openid-configuration.
 *
 * Compatible with Keycloak, Okta, Auth0, Dex, Google Workspace, and any
 * standard OpenID Connect IdP. Configure via the admin settings panel:
 *   - oidc_issuer_url   → e.g. https://keycloak.example.com/realms/myrealm
 *   - oidc_client_id
 *   - oidc_client_secret
 */
class OidcProvider extends AbstractProvider
{
    /** @var string[] */
    protected $scopes = ['openid', 'profile', 'email'];

    protected string $scopeSeparator = ' ';

    // ── Socialite AbstractProvider contract ───────────────────────────────────────

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discover('authorization_endpoint'), $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discover('token_endpoint');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->discover('userinfo_endpoint'), [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * @param array<string, mixed> $user
     */
    protected function mapUserToObject(array $user): User
    {
        // The `email_verified` claim is advisory — if false we still allow login
        // but the caller may choose to reject. The raw data is always available.
        return (new User)->setRaw($user)->map([
            'id'             => $user['sub'] ?? null,
            'name'           => $user['name'] ?? $user['preferred_username'] ?? ($user['email'] ?? ''),
            'email'          => $user['email'] ?? null,
            'email_verified' => $user['email_verified'] ?? null,
        ]);
    }

    // ── OIDC discovery ────────────────────────────────────────────────────────────

    /**
     * Return a single key from the OIDC discovery document.
     * The document is cached for 1 hour to avoid a round-trip on every login.
     *
     * @throws \RuntimeException if the issuer URL is not configured or the key is absent.
     */
    private function discover(string $key): string
    {
        $issuer = rtrim((string) config('emailalias.oidc_issuer_url', ''), '/');

        if (empty($issuer)) {
            throw new \RuntimeException(
                'OIDC provider is enabled but oidc_issuer_url is not configured. '
                . 'Set it in Admin → Settings → Authentication.'
            );
        }

        /** @var array<string, string> $document */
        $document = Cache::remember(
            'oidc_discovery_' . md5($issuer),
            3600,
            static function () use ($issuer): array {
                $response = Http::timeout(5)->get("{$issuer}/.well-known/openid-configuration");

                if ($response->failed()) {
                    throw new \RuntimeException(
                        "Failed to fetch OIDC discovery document from {$issuer}: HTTP {$response->status()}"
                    );
                }

                return $response->json();
            }
        );

        if (empty($document[$key])) {
            throw new \RuntimeException(
                "OIDC discovery document from {$issuer} is missing key: {$key}"
            );
        }

        return $document[$key];
    }
}
