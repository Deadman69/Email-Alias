<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    /** @var string|null null = use platform default */
    #[Validate('nullable|string|in:en,fr')]
    public ?string $locale = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->locale = $user->locale;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(AuditLogger $auditLogger): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $auditLogger->log(AuditEvent::ProfileUpdated, $user, [
            'fields' => array_keys($validated),
        ]);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Update the authenticated user's locale preference.
     * Applies immediately without a full page reload.
     */
    public function updateLocale(): void
    {
        $this->validateOnly('locale');

        $user = Auth::user();
        $user->locale = $this->locale ?: null;
        $user->save();

        // Apply immediately for the current request
        $locale = $this->locale ?: config('app.locale', 'en');
        if (in_array($locale, ['en', 'fr'], true)) {
            App::setLocale($locale);
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}
