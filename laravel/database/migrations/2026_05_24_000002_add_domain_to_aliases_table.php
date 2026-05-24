<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When multi-domain support is not configured, this column is null and the
     * platform falls back to config('emailalias.domain').  The full address is
     * still the canonical identifier — this column is a convenience for filtering.
     */
    public function up(): void
    {
        Schema::table('aliases', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('local_part');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::table('aliases', function (Blueprint $table) {
            $table->dropIndex(['domain']);
            $table->dropColumn('domain');
        });
    }
};
