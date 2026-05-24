<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machine / application-level API tokens.
     * Unlike personal_access_tokens these are not tied to a user account.
     * Managed exclusively by Super Admins.
     */
    public function up(): void
    {
        Schema::create('app_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('token', 64)->unique();  // SHA-256 hex of the plain token
            $table->json('abilities')->nullable();  // null = all abilities
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_tokens');
    }
};
