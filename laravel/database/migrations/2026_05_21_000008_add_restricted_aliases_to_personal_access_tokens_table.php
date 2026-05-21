<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            // null  = token has access to all of the user's aliases
            // array = token is restricted to the listed alias IDs
            // Note: expires_at is already provided by the base Sanctum migration.
            $table->json('restricted_alias_ids')->nullable()->after('abilities');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('restricted_alias_ids');
        });
    }
};
