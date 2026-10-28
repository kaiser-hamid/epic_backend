<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate from the create migration because sh_branches is created after sh_billing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_billing', function (Blueprint $table) {
            $table->foreignId('branch_id')->after('patient_master_id')->constrained('sh_branches');

            $table->index(['branch_id', 'billed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sh_billing', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'billed_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
