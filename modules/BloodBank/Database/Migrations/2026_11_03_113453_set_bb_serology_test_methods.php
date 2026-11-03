<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The method of each donor screening test, printed in the "Method" column of the Compatibility Test Report.
 * Blood group has no method on the report. Literal values, matched by code.
 */
return new class extends Migration
{
    private const array CODES = ['HBSAG', 'ANTI_HCV', 'ANTI_HIV', 'SYPHILIS'];

    public function up(): void
    {
        DB::table('bb_test_definitions')
            ->whereIn('code', self::CODES)
            ->update(['method' => 'Immunochromatographic Test', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('bb_test_definitions')
            ->whereIn('code', self::CODES)
            ->update(['method' => null, 'updated_at' => now()]);
    }
};
