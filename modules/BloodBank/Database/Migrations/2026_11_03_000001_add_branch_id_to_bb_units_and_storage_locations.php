<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blood stock is kept per branch: each branch has its own fridges and freezers and its own units. Storage
 * locations that existed before belong to the first branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bb_storage_locations', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('sh_branches');
        });

        DB::table('bb_storage_locations')
            ->whereNull('branch_id')
            ->update(['branch_id' => DB::table('sh_branches')->min('id')]);

        Schema::table('bb_storage_locations', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable(false)->change();
            $table->dropUnique(['code']);
            $table->unique(['branch_id', 'code']);
        });

        Schema::table('bb_units', function (Blueprint $table) {
            $table->foreignId('branch_id')->after('id')->constrained('sh_branches');

            $table->index(['branch_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bb_units', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status', 'expires_at']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('bb_storage_locations', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'code']);
            $table->unique(['code']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
