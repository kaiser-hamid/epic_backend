<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate from the create migration because sh_branches is created after bb_cases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bb_cases', function (Blueprint $table) {
            $table->foreignId('branch_id')->after('billing_id')->constrained('sh_branches');

            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bb_cases', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
