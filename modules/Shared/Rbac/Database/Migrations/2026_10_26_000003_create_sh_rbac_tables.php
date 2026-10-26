<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module');
            $table->string('group');
            $table->string('description');
            $table->timestampsTz();
        });

        Schema::create('sh_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('sh_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('sh_roles');
            $table->foreignId('permission_id')->constrained('sh_permissions');

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('sh_user_branch_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('branch_id')->constrained('sh_branches');
            $table->foreignId('permission_id')->constrained('sh_permissions');
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');

            $table->unique(['user_id', 'branch_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_user_branch_permissions');
        Schema::dropIfExists('sh_role_permissions');
        Schema::dropIfExists('sh_roles');
        Schema::dropIfExists('sh_permissions');
    }
};
