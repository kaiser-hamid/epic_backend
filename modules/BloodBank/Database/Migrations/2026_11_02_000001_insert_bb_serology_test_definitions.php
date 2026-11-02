<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The donor serology form writes to these rows by code (`SerologyTest`), so they ship with the schema instead of
 * a seeder. Values are literals so later enum changes do not rewrite history; re-running updates them by code.
 */
return new class extends Migration
{
    private const array CODES = ['BLOOD_GROUP', 'HBSAG', 'ANTI_HCV', 'ANTI_HIV', 'SYPHILIS'];

    public function up(): void
    {
        $screening = json_encode(['non_reactive', 'reactive']);
        $now = now();

        $definitions = [
            ['BLOOD_GROUP', 'Blood Group', json_encode(['A_POS', 'A_NEG', 'B_POS', 'B_NEG', 'AB_POS', 'AB_NEG', 'O_POS', 'O_NEG']), 'both'],
            ['HBSAG', 'HBsAg (Hepatitis B)', $screening, 'donor'],
            ['ANTI_HCV', 'Anti HCV (Hepatitis C)', $screening, 'donor'],
            ['ANTI_HIV', 'Anti HIV (1 & 2)', $screening, 'donor'],
            ['SYPHILIS', 'Syphilis', $screening, 'donor'],
        ];

        DB::table('bb_test_definitions')->upsert(
            array_map(fn (array $definition, int $index): array => [
                'code' => $definition[0],
                'name' => $definition[1],
                'result_type' => 'option',
                'options' => $definition[2],
                'applies_to' => $definition[3],
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $definitions, array_keys($definitions)),
            ['code'],
            ['name', 'result_type', 'options', 'applies_to', 'sort_order', 'is_active', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('bb_test_definitions')->whereIn('code', self::CODES)->delete();
    }
};
