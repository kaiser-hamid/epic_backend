<?php

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Models\Consumable;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Patient\Models\PatientMaster;

beforeEach(function (): void {
    $this->kept = [
        'user' => User::factory()->create(),
        'branch' => Branch::factory()->create(),
        'doctor' => Doctor::factory()->create(),
        'consumable' => Consumable::factory()->create(['current_balance' => 25]),
    ];

    PatientCase::factory()->create();
    DB::table('sh_number_sequences')->insert(['sequence' => 'invoice', 'scope' => 'global', 'last_value' => 7]);
    DB::table('sh_audit_logs')->insert(['action' => 'user.created', 'created_at' => now()]);
});

it('clears user-entered records across modules and keeps configuration', function (): void {
    $this->artisan('data:reset', ['--force' => true])->assertSuccessful();

    expect(Billing::query()->count())->toBe(0)
        ->and(PatientMaster::query()->count())->toBe(0)
        ->and(PatientCase::query()->count())->toBe(0)
        ->and(DB::table('sh_number_sequences')->count())->toBe(0)
        ->and(User::query()->whereKey($this->kept['user']->id)->exists())->toBeTrue()
        ->and(Branch::query()->whereKey($this->kept['branch']->id)->exists())->toBeTrue()
        ->and(Doctor::query()->whereKey($this->kept['doctor']->id)->exists())->toBeTrue()
        ->and($this->kept['consumable']->fresh()->current_balance)->toEqual(0);
});

it('keeps the append-only audit log unless told to clear it', function (): void {
    $this->artisan('data:reset', ['--force' => true])->assertSuccessful();

    expect(DB::table('sh_audit_logs')->count())->toBe(1);

    $this->artisan('data:reset', ['--force' => true, '--include-audit' => true])->assertSuccessful();

    expect(DB::table('sh_audit_logs')->count())->toBe(0);
});

it('does nothing when the operator declines the confirmation', function (): void {
    $this->artisan('data:reset')->expectsConfirmation('Clear these tables now?', 'no')->assertFailed();

    expect(PatientCase::query()->count())->toBe(1);
});
