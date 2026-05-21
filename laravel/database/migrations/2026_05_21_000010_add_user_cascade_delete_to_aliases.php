<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change aliases.user_id from nullOnDelete to cascadeOnDelete.
 *
 * With nullOnDelete, deleting a user left their aliases as ownerless rows that
 * continued to accept inbound emails, could never be managed, and leaked data.
 *
 * With cascadeOnDelete (DB level), deleting a user via SQL (e.g. direct admin
 * queries) will also remove their aliases at the database level.
 *
 * The application-level User::booted() hook ensures the cascade goes through
 * Eloquent first (Alias::booted() → InboundEmail::booted() → Attachment::booted()
 * → Storage::delete), so physical attachment files are always cleaned up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliases', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aliases', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
