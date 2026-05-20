<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create or promote a user to administrator';

    /**
     * Create a new admin user interactively.
     */
    public function handle(): int
    {
        $this->info('Create or promote a user to admin.');

        $email = text(
            label: 'Email address',
            required: true,
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email.',
        );

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $existingUser->update(['is_admin' => true]);
            $this->info("User [{$email}] has been promoted to admin.");

            return self::SUCCESS;
        }

        $name = text(label: 'Name', required: true);
        $pwd = password(label: 'Password', required: true);

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($pwd),
            'is_admin' => true,
        ]);

        $this->info("Admin user [{$email}] created successfully.");

        return self::SUCCESS;
    }
}
