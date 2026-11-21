<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A case can be served from stock: a stock unit joins the case as a stand-in donor (`source = stock`, `unit_id`),
 * pointing to the unit's original donor. A person may appear once per case as a real donor; a stock row is unique
 * per unit instead, so a stock bag of a donor who is also on the case is allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bb_case_donors', function (Blueprint $table) {
            $table->string('source')->default('donor')->after('donor_id');
            $table->foreignId('unit_id')->nullable()->after('source')->constrained('bb_units');

            $table->dropUnique(['case_id', 'donor_id']);
            $table->unique(['case_id', 'unit_id']);
        });

        DB::statement("CREATE UNIQUE INDEX bb_case_donors_case_id_donor_id_unique ON bb_case_donors (case_id, donor_id) WHERE source = 'donor'");
        DB::statement("ALTER TABLE bb_case_donors ADD CONSTRAINT bb_case_donors_stock_unit_check CHECK ((source = 'stock') = (unit_id IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bb_case_donors DROP CONSTRAINT bb_case_donors_stock_unit_check');
        DB::statement('DROP INDEX bb_case_donors_case_id_donor_id_unique');

        Schema::table('bb_case_donors', function (Blueprint $table) {
            $table->dropUnique(['case_id', 'unit_id']);
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('source');
            $table->unique(['case_id', 'donor_id']);
        });
    }
};
