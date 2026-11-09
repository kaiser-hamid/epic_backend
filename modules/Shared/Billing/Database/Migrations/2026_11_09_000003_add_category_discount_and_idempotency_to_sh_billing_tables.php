<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bills get a category (the one category of their items), a patient type, a discount and rounding breakdown, and
 * an idempotency key so a resubmitted form never saves a second bill.
 *
 * Each line repeats the bill's category, and the composite foreign key (billing_id, category) → sh_billing(id,
 * category) makes the database itself refuse a bill that mixes Blood Bank and other items.
 *
 * Money stays integer paisa: `amount` remains the payable, so `subtotal_amount` − `discount_amount` +
 * `rounding_amount` = `amount`. Existing bills are backfilled as undiscounted, unrounded and "General Patient".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_billing', function (Blueprint $table) {
            $table->string('category')->default('general')->after('branch_id')->index();
            $table->foreignId('patient_type_id')->nullable()->after('category')->constrained('sh_patient_types');
            $table->uuid('idempotency_key')->nullable()->unique()->after('invoice_no');
            $table->unsignedBigInteger('subtotal_amount')->default(0)->after('patient_type_id');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal_amount');
            $table->bigInteger('rounding_amount')->default(0)->after('discount_amount');

            $table->unique(['id', 'category']);
        });

        Schema::table('sh_billing_items', function (Blueprint $table) {
            $table->string('category')->default('general')->after('test_item_id');
            $table->unsignedSmallInteger('discount_rate_bp')->nullable()->after('amount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('discount_rate_bp');
            $table->unsignedBigInteger('net_amount')->default(0)->after('discount_amount');
        });

        DB::statement(<<<'SQL'
            UPDATE sh_billing SET category = 'blood_bank'
            WHERE EXISTS (
                SELECT 1 FROM sh_billing_items
                JOIN sh_test_items ON sh_test_items.id = sh_billing_items.test_item_id
                WHERE sh_billing_items.billing_id = sh_billing.id AND sh_test_items.category = 'blood_bank'
            )
            SQL);
        DB::statement('UPDATE sh_billing SET subtotal_amount = amount');
        DB::table('sh_billing')->update([
            'patient_type_id' => DB::table('sh_patient_types')->where('code', 'GENERAL')->value('id'),
        ]);
        DB::statement(<<<'SQL'
            UPDATE sh_billing_items SET net_amount = amount,
                category = (SELECT category FROM sh_billing WHERE sh_billing.id = sh_billing_items.billing_id)
            SQL);

        Schema::table('sh_billing', function (Blueprint $table) {
            $table->foreignId('patient_type_id')->nullable(false)->change();
        });

        Schema::table('sh_billing_items', function (Blueprint $table) {
            $table->foreign(['billing_id', 'category'], 'sh_billing_items_billing_category_foreign')
                ->references(['id', 'category'])
                ->on('sh_billing');
        });

        Schema::create('sh_billing_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('sh_billing');
            $table->foreignId('discount_policy_id')->constrained('sh_discount_policies');
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('rate_bp');
            $table->unsignedBigInteger('amount');
            $table->timestampsTz();

            $table->unique(['billing_id', 'discount_policy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_billing_discounts');

        Schema::table('sh_billing_items', function (Blueprint $table) {
            $table->dropForeign('sh_billing_items_billing_category_foreign');
            $table->dropColumn(['category', 'discount_rate_bp', 'discount_amount', 'net_amount']);
        });

        Schema::table('sh_billing', function (Blueprint $table) {
            $table->dropUnique(['id', 'category']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['category']);
            $table->dropConstrainedForeignId('patient_type_id');
            $table->dropColumn(['category', 'idempotency_key', 'subtotal_amount', 'discount_amount', 'rounding_amount']);
        });
    }
};
