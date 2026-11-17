<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The room a non-lab department receives patients in, per branch (e.g. Blood Bank at East Gate, room 900),
 * printed on the bill's Non-Lab Copy. Lab items print the branch's `lab_room_no` instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_department_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('sh_branches');
            $table->foreignId('department_id')->constrained('sh_departments');
            $table->string('room_no', 30);
            $table->timestampsTz();

            $table->unique(['branch_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_department_rooms');
    }
};
