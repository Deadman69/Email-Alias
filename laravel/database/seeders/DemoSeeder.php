<?php

namespace Database\Seeders;

use App\Enums\AliasType;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo data for development.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        $developer = User::factory()->create([
            'name'     => 'Paul Developer',
            'email'    => 'paul@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);

        $domain = config('emailalias.domain', 'example.com');

        $permanentAlias = Alias::factory()->create([
            'user_id'    => $developer->id,
            'address'    => "paul-dev@{$domain}",
            'local_part' => 'paul-dev',
            'type'       => AliasType::Permanent,
        ]);

        InboundEmail::factory()->count(5)->create(['alias_id' => $permanentAlias->id]);
        InboundEmail::factory()->read()->count(3)->create(['alias_id' => $permanentAlias->id]);

        Alias::factory()->session()->create(['user_id' => $developer->id]);
        Alias::factory()->withDuration('24h')->create(['user_id' => $developer->id]);
    }
}
