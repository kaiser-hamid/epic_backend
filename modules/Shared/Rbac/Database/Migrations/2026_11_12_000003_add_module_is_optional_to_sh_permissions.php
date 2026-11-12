<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks permissions of modules that only some branches run (filled by `permissions:sync`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_permissions', function (Blueprint $table) {
            $table->boolean('module_is_optional')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sh_permissions', function (Blueprint $table) {
            $table->dropColumn('module_is_optional');
        });
    }
};
