<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New staff accounts get ids from 1000 upward. Existing accounts keep their ids; if any already reach
 * 1000, numbering simply continues after the highest one. PostgreSQL only (the test database is SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), GREATEST(1000, (SELECT COALESCE(MAX(id), 0) + 1 FROM users)), false)");
    }

    /**
     * Ids already issued from 1000 cannot be reused, so the sequence only falls back to just after the
     * highest existing id.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT COALESCE(MAX(id), 0) + 1 FROM users), false)");
    }
};
