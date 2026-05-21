<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    /**
     * Redirect the user to the Azure AD OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('azure')->redirect();
    }

    /**
     * Handle the Azure AD callback, log the user in or create their account.
     */
    public function callback(AuditLogger $auditLogger): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
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

        $azureId = $socialUser->getId();

        // Find by azure_id first (fast path for returning SSO users)
        $user = User::where('azure_id', $azureId)->first();

        if (! $user) {
            $existingByEmail = User::where('email', $email)->first();

            if ($existingByEmail) {
                // Link SSO identity to an existing local account.
                // This is intentional — verified by matching email from the trusted SSO provider.
                $existingByEmail->azure_id = $azureId;
                $existingByEmail->save();

                $auditLogger->log(AuditEvent::SsoAccountLinked, $existingByEmail, [
                    'email'    => $email,
                    'provider' => 'azure',
                ]);

                $user = $existingByEmail;
            } else {
                // Create account automatically — email is verified via SSO
                $user = User::create([
                    'name'              => $socialUser->getName() ?? $email,
                    'email'             => $email,
                    'azure_id'          => $azureId,
                    'email_verified_at' => now(),
                    'password'          => null, // SSO-only account — no local password
                ]);
            }
        }

        Auth::login($user, remember: true);

        $auditLogger->log(AuditEvent::UserLogin, $user, [
            'method' => 'sso_azure',
        ]);

        return redirect()->intended(route('mailbox.dashboard'));
    }
}
