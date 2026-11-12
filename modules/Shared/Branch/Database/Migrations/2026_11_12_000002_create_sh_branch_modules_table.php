<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which optional modules (e.g. "blood-bank") each branch runs. Modules every branch has are not listed.
 * A super admin edits the list with the branch; changes are audited on the branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_branch_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('sh_branches');
            $table->string('module');
            $table->timestampsTz();

            $table->unique(['branch_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_branch_modules');
    }
};
