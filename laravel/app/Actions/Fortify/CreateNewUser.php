<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // We need to specify the user->id manually here because the user is not authenticated yet, and the AuditLogger defaults to using the currently authenticated user's ID if none is provided.
        (new AuditLogger(request()))->log(AuditEvent::UserRegister, $user, ['method' => 'web', 'role' => $user->role], $user->id);

        return $user;
    }
}
