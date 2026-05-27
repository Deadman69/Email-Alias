<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('email_id')->constrained('inbound_emails')->cascadeOnDelete();

            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size_bytes');

            // Storage location
            $table->string('disk', 64)->default('local');
            $table->string('path');

            // SHA256 or similar integrity checksum
            $table->string('checksum', 128)->nullable();

            $table->timestamps();

            // Useful indexes
            $table->index('mime_type');
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
