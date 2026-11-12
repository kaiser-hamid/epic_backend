<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modules become the top level of the permission screen: each catalog entry carries its module's heading and
 * position (filled by `permissions:sync`), and each role template belongs to one module.
 * Templates made before modules existed have no module; they are retired (soft deleted) so only the seeded,
 * module-scoped defaults remain. `sh_roles.module` stays nullable only for those retired rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_permissions', function (Blueprint $table) {
            $table->string('module_label')->default('');
            $table->unsignedSmallInteger('module_order')->default(0);
        });

        Schema::table('sh_roles', function (Blueprint $table) {
            $table->string('module')->nullable()->after('name');
        });

        DB::table('sh_roles')->whereNull('deleted_at')->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('sh_roles', function (Blueprint $table) {
            $table->dropColumn('module');
        });

        Schema::table('sh_permissions', function (Blueprint $table) {
            $table->dropColumn(['module_label', 'module_order']);
        });
    }
};
