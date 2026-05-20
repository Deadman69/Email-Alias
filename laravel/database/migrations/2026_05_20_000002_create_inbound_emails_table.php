<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('alias_id')->constrained()->cascadeOnDelete();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('subject')->default('(no subject)');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('headers')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['alias_id', 'deleted_at']);
            $table->index(['alias_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
};
