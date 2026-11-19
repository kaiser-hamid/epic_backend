<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A therapeutic phlebotomy: blood drawn from the patient and destroyed. One row per case; recording it closes the
 * case. Pre- and post-procedure vitals are optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_phlebotomies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('bb_cases');
            $table->unsignedSmallInteger('volume_ml');
            $table->unsignedSmallInteger('pre_pulse_bpm')->nullable();
            $table->unsignedSmallInteger('pre_bp_systolic')->nullable();
            $table->unsignedSmallInteger('pre_bp_diastolic')->nullable();
            $table->decimal('pre_temperature_f', 4, 1)->nullable();
            $table->unsignedSmallInteger('post_pulse_bpm')->nullable();
            $table->unsignedSmallInteger('post_bp_systolic')->nullable();
            $table->unsignedSmallInteger('post_bp_diastolic')->nullable();
            $table->decimal('post_temperature_f', 4, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('performed_at');
            $table->foreignId('performed_by')->constrained('users');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_phlebotomies');
    }
};
