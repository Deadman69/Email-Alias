<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();   // e.g. "example.com"
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
