<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Base Sanctum personal access tokens table.
// Replaces the need to run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // null  = token has access to all of the user's aliases
            // array = token is restricted to the listed alias IDs
            // Note: expires_at is already provided by the base Sanctum migration.
            $table->json('restricted_alias_ids')->nullable()->after('abilities');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
