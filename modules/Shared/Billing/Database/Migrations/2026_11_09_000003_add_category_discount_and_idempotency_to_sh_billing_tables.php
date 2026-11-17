<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills get a patient type, a discount and rounding breakdown, and an idempotency key so a resubmitted form never
 * saves a second bill.
 *
 * A bill may mix items of any category. Each line snapshots its item's category (`TestItemCategory`), which tells
 * the module that owns it (a Blood Bank line belongs to the bill's Blood Bank case).
 *
 * Money stays integer paisa: `amount` remains the payable, so `subtotal_amount` − `discount_amount` +
 * `rounding_amount` = `amount`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_billing', function (Blueprint $table) {
            $table->foreignId('patient_type_id')->after('branch_id')->constrained('sh_patient_types');
            $table->uuid('idempotency_key')->nullable()->unique()->after('invoice_no');
            $table->unsignedBigInteger('subtotal_amount')->default(0)->after('patient_type_id');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal_amount');
            $table->bigInteger('rounding_amount')->default(0)->after('discount_amount');
        });

        Schema::table('sh_billing_items', function (Blueprint $table) {
            $table->string('category')->after('test_item_id')->index();
            $table->unsignedSmallInteger('discount_rate_bp')->nullable()->after('amount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('discount_rate_bp');
            $table->unsignedBigInteger('net_amount')->default(0)->after('discount_amount');
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
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'discount_rate_bp', 'discount_amount', 'net_amount']);
        });

        Schema::table('sh_billing', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropConstrainedForeignId('patient_type_id');
            $table->dropColumn(['idempotency_key', 'subtotal_amount', 'discount_amount', 'rounding_amount']);
        });
    }
};
