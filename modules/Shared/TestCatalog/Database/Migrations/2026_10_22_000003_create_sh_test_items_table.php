<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_test_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('sh_departments');
            $table->string('code')->unique();
            $table->string('name')->index();
            $table->string('department_name')->comment('Redundant data for query optimization');
            $table->unsignedBigInteger('rate')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_test_items');
    }
};
