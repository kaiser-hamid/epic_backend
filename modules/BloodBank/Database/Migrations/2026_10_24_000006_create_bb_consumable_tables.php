<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_consumables', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('unit_of_measure');
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->decimal('current_balance', 12, 3)->default(0);
            $table->boolean('tracks_batch')->default(false);
            $table->boolean('tracks_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('bb_consumable_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained('bb_consumables');
            $table->string('batch_no');
            $table->date('expires_at')->nullable()->index();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->timestampsTz();

            $table->unique(['consumable_id', 'batch_no']);
        });

        Schema::create('bb_consumable_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained('bb_consumables');
            $table->foreignId('batch_id')->nullable()->constrained('bb_consumable_batches');
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['consumable_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_consumable_transactions');
        Schema::dropIfExists('bb_consumable_batches');
        Schema::dropIfExists('bb_consumables');
    }
};
