<?php

use App\Enums\AliasType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('address')->unique();
            $table->string('local_part');

            // FK to the domains table (nullable so aliases survive domain deletion
            // when the admin chooses to keep them rather than cascade).
            $table->string('domain_id', 26)->nullable()->index();
            $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();

            // Stores the actual domain name at creation time.
            // Used as the routing fallback when domain_id is null (domain was deleted).
            $table->string('domain')->index()->comment('Domain name at creation time — preserved if domain record is deleted');

            $table->string('type')->default(AliasType::Session->value);
            $table->string('duration')->nullable()->comment('e.g. 1h, 12h, 24h, 7d, 30d');
            // cascadeOnDelete: deleting a user removes all their aliases (and via Eloquent,
            // their emails + attachment files) — no ownerless data leaks.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable()->comment('Optional user-defined label');
            $table->timestamp('expires_at')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
            $table->index(['expires_at', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliases');
    }
};
