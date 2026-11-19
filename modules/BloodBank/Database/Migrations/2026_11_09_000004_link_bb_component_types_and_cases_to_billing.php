<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing now drives the case:
 *
 * - `bb_billable_items` says what a Blood Bank catalog item means to Blood Bank (with the item's code and name
 *   copied as redundant data): a component (`component_type_id`, `units_per_quantity` units per billed quantity,
 *   e.g. Platelet (4:1) = 4 platelets), a procedure (`service`: Autologous PRP, Therapeutic Phlebotomy) or the
 *   storage fee. Items without a row are plain services.
 * - `bb_case_components.test_item_id` repeats the billed item of each component line.
 * - A case is opened from exactly one bill: `bb_cases.billing_id` is required and unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_billable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_item_id')->unique()->constrained('sh_test_items');
            $table->string('test_item_code');
            $table->string('test_item_name');
            $table->string('kind')->comment('BillableItemKind');
            $table->foreignId('component_type_id')->nullable()->constrained('bb_component_types');
            $table->unsignedSmallInteger('units_per_quantity')->default(1);
            $table->string('service')->nullable()->comment('CaseService of a procedure item');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
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

        Schema::dropIfExists('bb_billable_items');
    }
};
