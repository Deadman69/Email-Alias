<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alias_shares', function (Blueprint $table) {
            $table->boolean('editable')->default(false)->after('shared_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('alias_shares', function (Blueprint $table) {
            $table->dropColumn('editable');
        });
    }
};
