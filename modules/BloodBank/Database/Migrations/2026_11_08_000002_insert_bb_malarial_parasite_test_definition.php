<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Malarial Parasite donor screening test (ICT method), written by the serology form by code
 * (`SerologyTest::MalarialParasite`). Literal values so later enum changes do not rewrite history.
 */
return new class extends Migration
{
    private const string CODE = 'MALARIAL_PARASITE';

    public function up(): void
    {
        $now = now();

        DB::table('bb_test_definitions')->upsert([[
            'code' => self::CODE,
            'name' => 'Malarial Parasite',
            'result_type' => 'option',
            'options' => json_encode(['not_found', 'found']),
            'method' => 'ICT method',
            'applies_to' => 'donor',
            'sort_order' => 6,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['code'], ['name', 'result_type', 'options', 'method', 'applies_to', 'sort_order', 'is_active', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('bb_test_definitions')->where('code', self::CODE)->delete();
    }
};
