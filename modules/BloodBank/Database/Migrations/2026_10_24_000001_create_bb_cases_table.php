<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no')->unique();
            $table->foreignId('patient_master_id')->constrained('sh_patient_master');
            $table->foreignId('billing_id')->nullable()->constrained('sh_billing');
            $table->string('care_type');
            $table->text('transfusion_reason')->nullable();
            $table->text('reference_note')->nullable();
            $table->string('status')->index();
            $table->timestampTz('supplied_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_cases');
    }
};
