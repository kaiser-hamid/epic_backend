<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_case_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('bb_cases');
            $table->foreignId('component_type_id')->constrained('bb_component_types');
            $table->unsignedSmallInteger('quantity');
            $table->timestampsTz();

            $table->unique(['case_id', 'component_type_id']);
        });

        Schema::create('bb_crossmatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('bb_cases');
            $table->foreignId('patient_sample_id')->constrained('bb_samples');
            $table->foreignId('unit_id')->constrained('bb_units');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('major_result');
            $table->string('minor_result');
            $table->text('remarks')->nullable();
            $table->foreignId('tested_by')->constrained('users');
            $table->timestampTz('tested_at');
            $table->string('status')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['patient_sample_id', 'unit_id', 'version']);
            $table->index(['case_id', 'is_current']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_crossmatches');
        Schema::dropIfExists('bb_case_components');
    }
};
