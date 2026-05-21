<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Enums\SsoProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * OAuth2 / OIDC SSO controller.
 *
 * Handles Azure AD (existing) and any generic OIDC provider (Keycloak, Okta, etc.).
 * SAML 2.0 is handled by SamlController — see routes/web.php.
 */
class SsoController extends Controller
{
    /**
     * Redirect the user to the configured SSO provider.
     */
    public function redirect(): RedirectResponse
    {
        $provider = $this->currentProvider();

        return Socialite::driver($provider->driver())->redirect();
    }

    /**
     * Handle the OAuth2 / OIDC callback from the SSO provider.
     */
    public function callback(AuditLogger $auditLogger): RedirectResponse
    {
        $provider = $this->currentProvider();

        try {
            $socialUser = Socialite::driver($provider->driver())->user();
        } catch (\Exception) {
            return redirect()->route('login')->withErrors([
                'email' => __('SSO authentication failed. Please try again.'),
            ]);
        }

        $email = $socialUser->getEmail();

        if (empty($email)) {
            return redirect()->route('login')->withErrors([
                'email' => __('No email address returned by the SSO provider.'),
            ]);
        }

        return $this->loginOrCreate(
            email:       $email,
            externalId:  $provider->value . ':' . $socialUser->getId(),
            name:        $socialUser->getName() ?? $email,
            provider:    $provider,
            legacyId:    $provider === SsoProvider::Azure ? $socialUser->getId() : null,
            auditLogger: $auditLogger,
        );
    }

    // ── Shared login logic ────────────────────────────────────────────────────────

    /**
     * Find the user by their SSO identity (or email as fallback), then log them in.
     * Creates a new account if none exists.
     */
    private function loginOrCreate(
        string      $email,
        string      $externalId,
        string      $name,
        SsoProvider $provider,
        ?string     $legacyId,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        // Primary lookup: external_id stores "provider:sub" to scope per provider
        $user = User::where('external_id', $externalId)->first();

        // Backward-compat: Azure users created before the multi-provider refactor
        // have their ID stored in the azure_id column instead of external_id.
        if (! $user && $provider === SsoProvider::Azure && $legacyId) {
            $user = User::where('azure_id', $legacyId)->first();
        }

        if (! $user) {
            $existingByEmail = User::where('email', $email)->first();

            if ($existingByEmail) {
                // Link this SSO identity to an existing local account.
                // Considered safe — email is verified by the trusted IdP.
                $existingByEmail->external_id = $externalId;

                if ($provider === SsoProvider::Azure && $legacyId) {
                    $existingByEmail->azure_id = $legacyId;
                }

                $existingByEmail->save();

                $auditLogger->log(AuditEvent::SsoAccountLinked, $existingByEmail, [
                    'email'    => $email,
                    'provider' => $provider->value,
                ]);

                $user = $existingByEmail;
            } else {
                // Auto-provision a new account — email is verified via the IdP.
                $user = User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'external_id'       => $externalId,
                    'azure_id'          => $provider === SsoProvider::Azure ? $legacyId : null,
                    'email_verified_at' => now(),
                    'password'          => null, // SSO-only account — no local password
                ]);
            }
        }

        // Reject deactivated users (SCIM or admin deprovisioning)
        if (! ($user->is_active ?? true)) {
            return redirect()->route('login')->withErrors([
                'email' => __('Your account has been deactivated.'),
            ]);
        }

        Auth::login($user, remember: true);

        $auditLogger->log(AuditEvent::UserLogin, $user, [
            'method' => 'sso_' . $provider->value,
        ]);

        return redirect()->intended(route('mailbox.dashboard'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function currentProvider(): SsoProvider
    {
        return SsoProvider::tryFrom(config('emailalias.sso_provider', 'azure'))
            ?? SsoProvider::Azure;
    }
}
