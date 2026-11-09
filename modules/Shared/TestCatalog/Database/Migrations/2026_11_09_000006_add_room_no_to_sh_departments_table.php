<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The room a department receives patients in, printed on the bill's Non Lab Copy (e.g. Blood Bank, room 900).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sh_departments', function (Blueprint $table) {
            $table->string('room_no', 30)->nullable()->after('name');
        });

        DB::table('sh_departments')->where('code', 'BB')->update(['room_no' => '900']);
    }

    public function down(): void
    {
        Schema::table('sh_departments', function (Blueprint $table) {
            $table->dropColumn('room_no');
        });
    }
};
