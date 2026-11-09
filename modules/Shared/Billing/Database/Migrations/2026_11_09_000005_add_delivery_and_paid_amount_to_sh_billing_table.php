<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bills record when the reports will be delivered and how much the patient paid at the counter (integer paisa,
 * never more than the payable `amount`). The due is `amount` − `paid_amount`. `delivery_at` is nullable only for
 * bills saved before it existed; new bills always set it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_billing', function (Blueprint $table) {
            $table->timestampTz('delivery_at')->nullable()->after('billed_at');
            $table->unsignedBigInteger('paid_amount')->default(0)->after('amount');
        });

        DB::statement('ALTER TABLE sh_billing ADD CONSTRAINT sh_billing_paid_amount_check CHECK (paid_amount <= amount)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sh_billing DROP CONSTRAINT sh_billing_paid_amount_check');

        Schema::table('sh_billing', function (Blueprint $table) {
            $table->dropColumn(['delivery_at', 'paid_amount']);
        });
    }
};
