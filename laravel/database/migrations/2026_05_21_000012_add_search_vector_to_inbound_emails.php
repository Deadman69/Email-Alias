<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->tsvector('search_vector')->nullable()->after('truncated_reason');
        });

        // GIN index for fast full-text search
        DB::statement('CREATE INDEX inbound_emails_search_vector_idx ON inbound_emails USING GIN (search_vector)');

        // Backfill existing rows
        DB::statement("
            UPDATE inbound_emails
            SET search_vector =
                setweight(to_tsvector('simple', coalesce(subject, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(from_address, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(from_name, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(body_text, '')), 'D')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inbound_emails_search_vector_idx');
        Schema::table('inbound_emails', function (Blueprint $table): void {
            $table->dropColumn('search_vector');
        });
    }
};
