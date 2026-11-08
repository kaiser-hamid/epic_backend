<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bb_donors', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('name');
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('religion')->nullable()->after('marital_status');
            $table->string('occupation', 100)->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('bb_donors', function (Blueprint $table) {
            $table->dropColumn(['father_name', 'marital_status', 'religion', 'occupation']);
        });
    }
};
