<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     *
     * For SSO users (no local password), password confirmation is skipped.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => Auth::user()->password
                ? $this->currentPasswordRules()
                : 'nullable',
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
