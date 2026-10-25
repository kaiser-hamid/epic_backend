<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->decimal('min_temperature_c', 5, 2)->nullable();
            $table->decimal('max_temperature_c', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('bb_component_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('shelf_life_hours');
            $table->string('storage_condition')->nullable();
            $table->string('default_location_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('bb_collections', function (Blueprint $table) {
            $table->id();
            $table->string('bag_no')->unique();
            $table->foreignId('case_donor_id')->constrained('bb_case_donors');
            $table->unsignedSmallInteger('volume_ml')->nullable();
            $table->timestampTz('collected_at');
            $table->foreignId('collected_by')->constrained('users');

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('bb_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_no')->unique();
            $table->foreignId('collection_id')->constrained('bb_collections');
            $table->foreignId('parent_unit_id')->nullable()->constrained('bb_units');
            $table->foreignId('component_type_id')->constrained('bb_component_types');
            $table->foreignId('case_id')->nullable()->index()->constrained('bb_cases')->comment('Assigning to patient case');
            $table->string('blood_group')->nullable()->index();
            $table->unsignedSmallInteger('volume_ml')->nullable();
            $table->string('status');
            $table->foreignId('storage_location_id')->nullable()->constrained('bb_storage_locations');
            $table->timestampTz('prepared_at');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('bb_unit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('bb_units');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('from_location_id')->nullable()->constrained('bb_storage_locations');
            $table->foreignId('to_location_id')->nullable()->constrained('bb_storage_locations');
            $table->foreignId('case_id')->nullable()->constrained('bb_cases');
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_unit_movements');
        Schema::dropIfExists('bb_units');
        Schema::dropIfExists('bb_collections');
        Schema::dropIfExists('bb_component_types');
        Schema::dropIfExists('bb_storage_locations');
    }
};
