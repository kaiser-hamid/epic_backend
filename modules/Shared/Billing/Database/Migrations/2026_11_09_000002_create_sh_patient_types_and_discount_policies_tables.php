<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discounts are chosen through the bill's patient type: a type groups the discount policies staff may pick from
 * (one per bill). Rates are basis points (2500 = 25 %). Ships with the "General Patient" type and its "General 25%"
 * policy because every bill needs a patient type; re-running leaves edited rows alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_patient_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('sh_discount_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('rate_bp');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('sh_patient_type_discount_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_type_id')->constrained('sh_patient_types');
            $table->foreignId('discount_policy_id')->constrained('sh_discount_policies');
            $table->timestampsTz();

            $table->unique(['patient_type_id', 'discount_policy_id']);
        });

        $now = now();

        DB::table('sh_patient_types')->insertOrIgnore([
            'code' => 'GENERAL',
            'name' => 'General Patient',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sh_discount_policies')->insertOrIgnore([
            'code' => 'GENERAL_25',
            'name' => 'General 25%',
            'type' => 'percentage',
            'rate_bp' => 2500,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sh_patient_type_discount_policies')->insertOrIgnore([
            'patient_type_id' => DB::table('sh_patient_types')->where('code', 'GENERAL')->value('id'),
            'discount_policy_id' => DB::table('sh_discount_policies')->where('code', 'GENERAL_25')->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_patient_type_discount_policies');
        Schema::dropIfExists('sh_discount_policies');
        Schema::dropIfExists('sh_patient_types');
    }
};
