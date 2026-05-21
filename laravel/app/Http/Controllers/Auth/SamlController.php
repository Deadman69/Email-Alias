<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * SAML 2.0 Service Provider controller.
 *
 * ── Dependency ────────────────────────────────────────────────────────────────
 * This controller requires the aacotroneo/laravel-saml2 package:
 *
 *   composer require aacotroneo/laravel-saml2
 *   php artisan vendor:publish --provider="Aacotroneo\Saml2\Saml2ServiceProvider"
 *
 * After installing, replace the abort(501) stubs below with the actual
 * Aacotroneo\Saml2\Saml2Auth calls and uncomment the constructor injection.
 *
 * ── Routes ────────────────────────────────────────────────────────────────────
 * GET  /auth/saml/metadata  → SP metadata XML (share with the IdP)
 * GET  /auth/saml/login     → redirect to IdP SSO URL
 * POST /auth/saml/acs       → Assertion Consumer Service (IdP POST-binding callback)
 * GET  /auth/saml/sls       → Single Logout Service (optional)
 */
class SamlController extends Controller
{
    // Uncomment when aacotroneo/laravel-saml2 is installed:
    // public function __construct(private readonly \Aacotroneo\Saml2\Saml2Auth $saml2Auth) {}

    /**
     * Serve the SP metadata XML.
     * Register this URL in the IdP as your Service Provider entity.
     */
    public function metadata(): Response
    {
        $this->requireSamlPackage();

        // $metadata = $this->saml2Auth->getMetadata();
        // return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Redirect the user to the IdP SSO URL (SP-initiated login).
     */
    public function login(): RedirectResponse
    {
        $this->requireSamlPackage();

        // return redirect($this->saml2Auth->login(route('dashboard')));
    }

    /**
     * Assertion Consumer Service — process the SAMLResponse POST from the IdP.
     */
    public function acs(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->requireSamlPackage();

        // $errors = $this->saml2Auth->acs();
        //
        // if (! empty($errors)) {
        //     return redirect()->route('login')->withErrors([
        //         'email' => __('SAML authentication failed: ') . implode(', ', $errors),
        //     ]);
        // }
        //
        // $samlUser = $this->saml2Auth->getSaml2User();
        // $email    = $samlUser->getUserId();   // NameID — typically the email
        // $attrs    = $samlUser->getAttributes();
        // $name     = $attrs['displayName'][0] ?? $attrs['givenName'][0] ?? $email;
        //
        // return $this->loginOrCreate($email, 'saml:' . $samlUser->getNameId(), $name, $auditLogger);
    }

    /**
     * Single Logout Service — handle IdP-initiated logout.
     */
    public function sls(AuditLogger $auditLogger): RedirectResponse
    {
        $this->requireSamlPackage();

        // $this->saml2Auth->sls(config('app.url'));
        // Auth::logout();
        // return redirect('/');
    }

    // ── Shared login logic ────────────────────────────────────────────────────────

    /**
     * Find or create the user, then log them in.
     * Extracted so it can be called after the SAML assertion is parsed.
     */
    private function loginOrCreate(
        string $email,
        string $externalId,
        string $name,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $user = User::where('external_id', $externalId)->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if (! ($user->is_active ?? true)) {
                return redirect()->route('login')->withErrors([
                    'email' => __('Your account has been deactivated.'),
                ]);
            }

            // Link SAML identity if not already stored
            if ($user->external_id !== $externalId) {
                $user->external_id = $externalId;
                $user->save();

                $auditLogger->log(AuditEvent::SsoAccountLinked, $user, [
                    'email'    => $email,
                    'provider' => 'saml',
                ]);
            }
        } else {
            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'external_id'       => $externalId,
                'email_verified_at' => now(),
                'password'          => null,
            ]);
        }

        Auth::login($user, remember: true);
        $auditLogger->log(AuditEvent::UserLogin, $user, ['method' => 'sso_saml']);

        return redirect()->intended(route('mailbox.dashboard'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException if the SAML package is not installed.
     */
    private function requireSamlPackage(): void
    {
        if (! class_exists(\Aacotroneo\Saml2\Saml2Auth::class)) {
            abort(501, 'SAML support requires "composer require aacotroneo/laravel-saml2". See README_TODO.md.');
        }
    }
}
