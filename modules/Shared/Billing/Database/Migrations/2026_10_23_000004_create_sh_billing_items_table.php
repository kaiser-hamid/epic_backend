<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('sh_billing');
            $table->foreignId('test_item_id')->constrained('sh_test_items');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('rate');
            $table->unsignedBigInteger('amount');
            $table->timestampsTz();

            $table->unique(['billing_id', 'test_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_billing_items');
    }
};
