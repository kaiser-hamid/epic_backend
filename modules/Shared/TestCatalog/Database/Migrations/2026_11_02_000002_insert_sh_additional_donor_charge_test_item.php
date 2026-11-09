<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The fixed catalog item for an extra Blood Bank donor (code `BB-ADDL-DONOR`), billed on New Bill Entry like any
 * general item (it opens no case); the bill is then attached to the donor on Add Donor. Values are
 * literals; re-running leaves an edited rate alone.
 */
return new class extends Migration
{
    private const string CODE = 'BB-ADDL-DONOR';

    public function up(): void
    {
        $now = now();

        DB::table('sh_departments')->insertOrIgnore([
            'code' => 'BB',
            'name' => 'Blood Bank',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sh_test_items')->insertOrIgnore([
            'department_id' => DB::table('sh_departments')->where('code', 'BB')->value('id'),
            'code' => self::CODE,
            'name' => 'Additional Donor Charge',
            'department_name' => 'Blood Bank',
            'rate' => 100000,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('sh_test_items')->where('code', self::CODE)->delete();
    }
};
