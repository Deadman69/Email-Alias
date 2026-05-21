<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliases', function (Blueprint $table): void {
            $table->string('webhook_url')->nullable()->after('label');
            // Stored in plaintext — used to compute HMAC-SHA256 signatures on delivery
            $table->string('webhook_secret', 64)->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('aliases', function (Blueprint $table): void {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
    }
};
