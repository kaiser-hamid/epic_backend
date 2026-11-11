<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Auth\Enums\Designation;

/**
 * `users.designation` becomes a `Designation` value. Free-text titles that match an enum case
 * ("Lab Technician" → `lab_technician`) are converted; anything else is cleared, because the model
 * cast would reject it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('designation')->orderBy('id')->each(function (object $user): void {
            $designation = Designation::tryFrom(preg_replace("/\s+/", '_', Str::lower(trim($user->designation))));

            DB::table('users')->where('id', $user->id)->update(['designation' => $designation?->value]);
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('designation')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'designation' => Designation::tryFrom($user->designation)?->label() ?? $user->designation,
            ]);
        });
    }
};
