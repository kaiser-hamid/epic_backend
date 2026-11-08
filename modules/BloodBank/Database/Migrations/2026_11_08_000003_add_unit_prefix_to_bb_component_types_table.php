<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prefix of the unit number per component code, so a separated bag is numbered like BT2611020003 instead of
     * B2611020003-PLATELET.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'WB' => 'BH',
        'PCV' => 'BC',
        'FP' => 'BP',
        'CRYO' => 'BR',
        'PLATELET' => 'BT',
        'PLASMA' => 'BS',
    ];

    public function up(): void
    {
        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->string('unit_prefix', 4)->nullable()->unique()->after('code');
        });

        foreach (self::PREFIXES as $code => $prefix) {
            DB::table('bb_component_types')->where('code', $code)->update(['unit_prefix' => $prefix]);
        }
    }

    public function down(): void
    {
        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->dropColumn('unit_prefix');
        });
    }
};
