<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `category` decides which kind of bill an item goes on (`TestItemCategory`). `discount_rate_bp` overrides the
 * bill's discount policy for this item, in basis points (1000 = 10 %, 0 = never discounted); null follows the policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_test_items', function (Blueprint $table) {
            $table->string('category')->default('general')->after('department_name')->index();
            $table->unsignedSmallInteger('discount_rate_bp')->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('sh_test_items', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'discount_rate_bp']);
        });
    }
};
