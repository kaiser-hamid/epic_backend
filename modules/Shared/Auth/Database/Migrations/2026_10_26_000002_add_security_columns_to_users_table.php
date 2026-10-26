<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->boolean('is_super_admin')->default(false);
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestampTz('password_changed_at')->nullable();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('sh_branches');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'failed_login_attempts', 'locked_until', 'must_change_password', 'password_changed_at']);
            $table->string('username')->nullable()->change();
        });
    }
};
