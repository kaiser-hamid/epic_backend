<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence');
            $table->string('scope')->comment('e.g. global, or a branch id for per-branch counters');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestampsTz();

            $table->unique(['sequence', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_number_sequences');
    }
};
