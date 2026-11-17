<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing now drives the case:
 *
 * - Every component type is created from a Blood Bank catalog item (`test_item_id`, with the item's code and name
 *   copied as redundant data); the type keeps its own short `code`, prefix and shelf life for preparation and labels.
 *   A case's components are the invoice lines whose item is a component type's item.
 * - `bb_case_components.test_item_id` repeats the billed item of each component line.
 * - A case is opened from exactly one bill: `bb_cases.billing_id` is required and unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bb_component_types', function (Blueprint $table) {
            $table->foreignId('test_item_id')->after('id')->unique()->constrained('sh_test_items');
            $table->string('test_item_code')->after('test_item_id');
            $table->string('test_item_name')->after('test_item_code');
        });

        Schema::table('bb_case_components', function (Blueprint $table) {
            $table->foreignId('test_item_id')->nullable()->after('component_type_id')->constrained('sh_test_items');
        });

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
};
