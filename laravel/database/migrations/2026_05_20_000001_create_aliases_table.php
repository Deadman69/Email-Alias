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
            $table->string('type')->default(AliasType::Session->value);
            $table->string('duration')->nullable()->comment('e.g. 1h, 12h, 24h, 7d, 30d');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable()->comment('Optional user-defined label');
            $table->timestamp('expires_at')->nullable();
            $table->string('webhook_url')->nullable()
            $table->string('webhook_secret', 64)->nullable()
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
