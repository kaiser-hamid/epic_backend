<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_donors', function (Blueprint $table) {
            $table->id();
            $table->string('donor_no')->unique();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('blood_group')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('national_id')->nullable()->index();
            $table->boolean('is_regular_volunteer')->nullable()->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('bb_case_donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('bb_cases');
            $table->foreignId('donor_id')->constrained('bb_donors');
            $table->foreignId('billing_id')->nullable()->constrained('sh_billing');
            $table->unsignedSmallInteger('sequence_no');
            $table->string('status')->index();
            $table->text('deferral_reason')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users');
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['case_id', 'donor_id']);
            $table->unique(['case_id', 'sequence_no']);
        });

        Schema::create('bb_donor_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_donor_id')->unique()->constrained('bb_case_donors');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('hemoglobin_g_dl', 4, 1)->nullable();
            $table->unsignedSmallInteger('pre_pulse_bpm')->nullable();
            $table->unsignedSmallInteger('pre_bp_systolic')->nullable();
            $table->unsignedSmallInteger('pre_bp_diastolic')->nullable();
            $table->decimal('pre_temperature_f', 4, 1)->nullable();
            $table->foreignId('examined_by')->constrained('users');
            $table->timestampTz('examined_at');
            $table->unsignedSmallInteger('post_pulse_bpm')->nullable();
            $table->unsignedSmallInteger('post_bp_systolic')->nullable();
            $table->unsignedSmallInteger('post_bp_diastolic')->nullable();
            $table->decimal('post_temperature_f', 4, 1)->nullable();
            $table->foreignId('post_recorded_by')->nullable()->constrained('users');
            $table->timestampTz('post_recorded_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_donor_screenings');
        Schema::dropIfExists('bb_case_donors');
        Schema::dropIfExists('bb_donors');
    }
};
