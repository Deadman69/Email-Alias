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
 * Settings are loaded at request time from the platform DB (via config('emailalias.saml_*')),
 * which is populated by the BootstrapSettings middleware before any controller runs.
 * This avoids the boot-time config limitation of aacotroneo/laravel-saml2.
 *
 * ── Routes ────────────────────────────────────────────────────────────────────
 * GET  /auth/saml/metadata  → SP metadata XML (share with the IdP)
 * GET  /auth/saml/login     → redirect to IdP SSO URL
 * POST /auth/saml/acs       → Assertion Consumer Service (IdP POST-binding callback)
 * GET  /auth/saml/sls       → Single Logout Service (optional)
 */
class SamlController extends Controller
{
    /**
     * Serve the SP metadata XML.
     * Register this URL in the IdP as your Service Provider entity.
     */
    public function metadata(): Response
    {
        $auth     = $this->buildAuth();
        $settings = $auth->getSettings();
        $metadata = $settings->getSPMetadata();

        $errors = $settings->validateMetadata($metadata);
        if (! empty($errors)) {
            abort(500, 'Invalid SP metadata: ' . implode(', ', $errors));
        }

        return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Redirect the user to the IdP SSO URL (SP-initiated login).
     */
    public function login(): RedirectResponse
    {
        $url = $this->buildAuth()->login(route('dashboard'), [], false, false, true);

        return redirect()->away($url);
    }

    /**
     * Assertion Consumer Service — process the SAMLResponse POST from the IdP.
     */
    public function acs(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $auth = $this->buildAuth();
        $auth->processResponse();

        $errors = $auth->getErrors();

        if (! empty($errors)) {
            return redirect()->route('login')->withErrors([
                'email' => __('SAML authentication failed: ') . implode(', ', $errors),
            ]);
        }

        if (! $auth->isAuthenticated()) {
            return redirect()->route('login')->withErrors([
                'email' => __('SAML authentication failed: response not authenticated.'),
            ]);
        }

        $attrs = $auth->getAttributes();

        // ── Email resolution ──────────────────────────────────────────────────
        $emailAttr = (string) config('emailalias.saml_attr_email', '');
        $email     = ($emailAttr && isset($attrs[$emailAttr][0]))
            ? $attrs[$emailAttr][0]
            : $auth->getNameId(); // fallback: NameID is usually the email

        // ── Display name resolution ───────────────────────────────────────────
        $nameAttr = (string) config('emailalias.saml_attr_name', '');
        if ($nameAttr && isset($attrs[$nameAttr][0])) {
            $name = $attrs[$nameAttr][0];
        } else {
            $name = $attrs['displayName'][0]
                ?? $attrs['http://schemas.microsoft.com/identity/claims/displayname'][0]
                ?? $attrs['givenName'][0]
                ?? $email;
        }

        return $this->loginOrCreate((string) $email, 'saml:' . $email, (string) $name, $auditLogger);
    }

    /**
     * Single Logout Service — handle IdP-initiated logout.
     */
    public function sls(AuditLogger $auditLogger): RedirectResponse
    {
        $auth = $this->buildAuth();

        $auth->processSLO(false, null, false, function () use ($auditLogger): void {
            $auditLogger->log(AuditEvent::UserLogout, Auth::user());
            Auth::logout();
        });

        $errors = $auth->getErrors();

        if (! empty($errors)) {
            return redirect('/')->withErrors(['saml' => implode(', ', $errors)]);
        }

        return redirect('/');
    }

    // ── Shared login logic ────────────────────────────────────────────────────────

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

    // ── Auth builder ──────────────────────────────────────────────────────────────

    /**
     * Build a OneLogin SAML Auth instance from current platform settings.
     * Called on every request so DB-sourced config is always fresh.
     */
    private function buildAuth(): \OneLogin\Saml2\Auth
    {
        $spEntityId = (string) config('emailalias.saml_sp_entity_id')
            ?: route('saml.metadata');

        $spCert = (string) config('emailalias.saml_sp_x509cert', '');
        $spKey  = (string) config('emailalias.saml_sp_private_key', '');
        $hasSp  = $spCert !== '' && $spKey !== '';

        return new \OneLogin\Saml2\Auth([
            'strict' => app()->isProduction(),
            'debug'  => (bool) config('app.debug'),
            'baseurl' => config('app.url'),

            'sp' => [
                'entityId' => $spEntityId,
                'assertionConsumerService' => [
                    'url'     => route('saml.acs'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'singleLogoutService' => [
                    'url'     => route('saml.sls'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'x509cert'   => $spCert,
                'privateKey' => $spKey,
            ],

            'idp' => [
                'entityId' => (string) config('emailalias.saml_idp_entity_id', ''),
                'singleSignOnService' => [
                    'url'     => (string) config('emailalias.saml_idp_sso_url', ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'singleLogoutService' => [
                    'url'     => (string) config('emailalias.saml_idp_slo_url', ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => (string) config('emailalias.saml_idp_certificate', ''),
            ],

            'security' => [
                // Signing is enabled only when the SP cert + key are both configured.
                'authnRequestsSigned'   => $hasSp,
                'logoutRequestSigned'   => $hasSp,
                'logoutResponseSigned'  => $hasSp,
                'signMetadata'          => $hasSp,
                'wantMessagesSigned'    => false,
                'wantAssertionsSigned'  => true,
                'wantNameId'            => true,
                'wantNameIdEncrypted'   => false,
                'wantAssertionsEncrypted' => false,
                'signatureAlgorithm'    => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm'       => 'http://www.w3.org/2001/04/xmlenc#sha256',
            ],
        ]);
    }
}
