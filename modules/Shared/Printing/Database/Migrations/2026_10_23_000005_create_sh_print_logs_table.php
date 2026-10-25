<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->string('printable_type');
            $table->unsignedBigInteger('printable_id');
            $table->foreignId('printed_by')->constrained('users');
            $table->timestampTz('printed_at');
            $table->boolean('is_reprint')->default(false);
            $table->text('reprint_reason')->nullable();
            $table->timestampsTz();

            $table->index(['printable_type', 'printable_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_print_logs');
    }
};
