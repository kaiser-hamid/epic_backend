<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_billing', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('patient_master_id')->constrained('sh_patient_master');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('status')->index();
            $table->timestampTz('billed_at');
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_billing');
    }
};
