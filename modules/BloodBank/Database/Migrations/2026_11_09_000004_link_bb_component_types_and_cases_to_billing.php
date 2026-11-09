<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing now drives the case:
 *
 * - Every component type is created from a Blood Bank catalog item (`test_item_id`, with the item's code and name
 *   copied as redundant data); the type keeps its own short `code`, prefix and shelf life for preparation and labels.
 *   A case's components are the invoice lines whose item is a component type's item.
 * - `bb_case_components.test_item_id` repeats the billed item of each component line.
 * - A case is opened from exactly one bill: `bb_cases.billing_id` is required and unique.
 *
 * Existing component types are matched to an active Blood Bank item by name. A type with no match stops the
 * migration: on development data, run `migrate:fresh --seed` instead.
 */
return new class extends Migration
{
    /**
     * Catalog item name for each placeholder component type (`ComponentTypeSeeder`).
     *
     * @var array<string, string>
     */
    private const array ITEM_NAMES = [
        'WB' => 'Whole Blood Processing',
        'PCV' => 'Packed Red Cells',
        'FP' => 'Fresh Frozen Plasma',
        'CRYO' => 'Cryoprecipitate',
        'PLATELET' => 'Platelet Concentrate',
        'PLASMA' => 'Plasma',
    ];

    public function up(): void
    {
        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->foreignId('test_item_id')->nullable()->after('id')->unique()->constrained('sh_test_items');
            $table->string('test_item_code')->nullable()->after('test_item_id');
            $table->string('test_item_name')->nullable()->after('test_item_code');
        });

        $this->linkComponentTypesToTestItems();

        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->foreignId('test_item_id')->nullable(false)->change();
            $table->string('test_item_code')->nullable(false)->change();
            $table->string('test_item_name')->nullable(false)->change();
        });

        Schema::table('bb_case_components', function (Blueprint $table) {
            $table->foreignId('test_item_id')->nullable()->after('component_type_id')->constrained('sh_test_items');
        });

        DB::statement(<<<'SQL'
            UPDATE bb_case_components SET test_item_id = (
                SELECT test_item_id FROM bb_component_types WHERE bb_component_types.id = bb_case_components.component_type_id
            )
            SQL);

        Schema::table('bb_cases', function (Blueprint $table) {
            $table->foreignId('billing_id')->nullable(false)->change();
            $table->unique('billing_id');
        });
    }

    public function down(): void
    {
        Schema::table('bb_cases', function (Blueprint $table) {
            $table->dropUnique(['billing_id']);
            $table->foreignId('billing_id')->nullable()->change();
        });

        Schema::table('bb_case_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('test_item_id');
        });

        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('test_item_id');
            $table->dropColumn(['test_item_code', 'test_item_name']);
        });
    }

    private function linkComponentTypesToTestItems(): void
    {
        foreach (DB::table('bb_component_types')->get(['id', 'code', 'name']) as $componentType) {
            $item = DB::table('sh_test_items')
                ->where('category', 'blood_bank')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereIn('name', array_filter([self::ITEM_NAMES[$componentType->code] ?? null, $componentType->name]))
                ->whereNotIn('id', DB::table('bb_component_types')->whereNotNull('test_item_id')->select('test_item_id'))
                ->first(['id', 'code', 'name'])
                ?? throw new RuntimeException("Component type {$componentType->code} has no Blood Bank test item to be created from; run `php artisan migrate:fresh --seed` on development data.");

            DB::table('bb_component_types')->where('id', $componentType->id)->update([
                'test_item_id' => $item->id,
                'test_item_code' => $item->code,
                'test_item_name' => $item->name,
            ]);
        }
    }
};
