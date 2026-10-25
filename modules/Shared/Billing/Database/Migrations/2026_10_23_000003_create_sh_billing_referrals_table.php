<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sh_billing_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->unique()->constrained('sh_billing');
            $table->foreignId('doctor_id')->nullable()->constrained('sh_doctors');
            $table->foreignId('second_doctor_id')->nullable()->constrained('sh_doctors');
            $table->boolean('is_self_referral')->default(false);
            $table->string('remark')->nullable();
            $table->foreignId('referred_by_staff_id')->nullable()->constrained('users');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sh_billing_referrals');
    }
};
