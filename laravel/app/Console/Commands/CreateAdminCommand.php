<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {--super-admin : Create a Super Admin instead of a regular Admin}';

    protected $description = 'Create a new user or promote an existing user to Admin or Super Admin';

    public function handle(): int
    {
        $targetRole = $this->option('super-admin') ? Role::SuperAdmin : Role::Admin;

        $this->info("Creating / promoting user to: {$targetRole->label()}");

        $email = text(
            label: 'Email address',
            required: true,
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email address.',
        );

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->role === $targetRole) {
                $this->warn("User [{$email}] already has the role '{$targetRole->label()}'.");

                return self::SUCCESS;
            }

            // If user already has a higher role, confirm downgrade
            if ($existingUser->role === Role::SuperAdmin && $targetRole === Role::Admin) {
                $confirmed = confirm(
                    label: "User [{$email}] is currently a Super Admin. Downgrade to Admin?",
                    default: false,
                );

                if (! $confirmed) {
                    $this->info('Aborted.');

                    return self::SUCCESS;
                }
            }

            $existingUser->update(['role' => $targetRole]);
            $this->info("User [{$email}] promoted to {$targetRole->label()}.");

            return self::SUCCESS;
        }

        // Create a new user
        $name = text(label: 'Name', required: true);
        $pwd  = password(label: 'Password', required: true, validate: fn ($v) => strlen($v) >= 8 ? null : 'Minimum 8 characters.');

        User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($pwd),
            'role'              => $targetRole,
            'email_verified_at' => now(),
        ]);

        $this->info("{$targetRole->label()} user [{$email}] created successfully.");

        return self::SUCCESS;
    }
}
