<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_test_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('result_type');
            $table->string('unit')->nullable();
            $table->string('reference_range')->nullable();
            $table->string('method')->nullable();
            $table->jsonb('options')->nullable();
            $table->string('applies_to');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('bb_samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_no')->unique();
            $table->foreignId('case_id')->constrained('bb_cases');
            $table->foreignId('case_donor_id')->nullable()->constrained('bb_case_donors');
            $table->string('subject_type');
            $table->timestampTz('collected_at');
            $table->foreignId('collected_by')->constrained('users');
            $table->string('status')->index();
            $table->timestampsTz();
        });

        Schema::create('bb_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('bb_samples');
            $table->foreignId('test_definition_id')->constrained('bb_test_definitions');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->text('result_value')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('tested_by')->constrained('users');
            $table->timestampTz('tested_at');
            $table->string('status')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['sample_id', 'test_definition_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_test_results');
        Schema::dropIfExists('bb_samples');
        Schema::dropIfExists('bb_test_definitions');
    }
};
