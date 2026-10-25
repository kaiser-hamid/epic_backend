<?php

use Illuminate\Database\QueryException;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Billing\Models\BillingReferral;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\TestItem;

it('stores a patient master row mirroring the registration form', function () {
    $patient = PatientMaster::factory()->create();

    expect($patient->title)->not->toBeNull()
        ->and($patient->age_years)->toBeInt()
        ->and($patient->marital_status)->not->toBeNull();
});

it('creates a bill for a patient', function () {
    $billing = Billing::factory()->create();

    expect($billing->patient)->toBeInstanceOf(PatientMaster::class)
        ->and($billing->invoice_no)->toStartWith('INV-');
});

it('takes department, code and name from the test item catalog', function () {
    $department = Department::factory()->create(['name' => 'Pathology']);
    $testItem = TestItem::factory()->for($department)->create(['code' => 'CBC', 'name' => 'Complete Blood Count', 'rate' => 50000]);
    $billing = Billing::factory()->create();

    $line = BillingItem::factory()->create([
        'billing_id' => $billing->id,
        'test_item_id' => $testItem->id,
        'quantity' => 2,
        'rate' => $testItem->rate,
        'amount' => $testItem->rate * 2,
    ]);

    expect($line->testItem->code)->toBe('CBC')
        ->and($line->testItem->department->name)->toBe('Pathology')
        ->and($billing->items)->toHaveCount(1)
        ->and($line->amount)->toBe(100000);
});

it('keeps the billed rate when the catalog rate changes later', function () {
    $testItem = TestItem::factory()->create(['rate' => 50000]);
    $line = BillingItem::factory()->create(['test_item_id' => $testItem->id, 'rate' => 50000, 'amount' => 50000]);

    $testItem->update(['rate' => 80000]);

    expect($line->fresh()->rate)->toBe(50000)
        ->and($line->fresh()->testItem->rate)->toBe(80000);
});

it('does not allow the same test item twice on one bill', function () {
    $billing = Billing::factory()->create();
    $testItem = TestItem::factory()->create();

    BillingItem::factory()->create(['billing_id' => $billing->id, 'test_item_id' => $testItem->id]);
    BillingItem::factory()->create(['billing_id' => $billing->id, 'test_item_id' => $testItem->id]);
})->throws(QueryException::class);

it('links a bill to its referring doctors and staff', function () {
    $billing = Billing::factory()->create();
    $secondDoctor = Doctor::factory()->create();
    $referral = BillingReferral::factory()->create([
        'billing_id' => $billing->id,
        'second_doctor_id' => $secondDoctor->id,
        'remark' => 'Urgent',
    ]);

    expect($billing->referral->is($referral))->toBeTrue()
        ->and($referral->doctor)->toBeInstanceOf(Doctor::class)
        ->and($referral->secondDoctor->is($secondDoctor))->toBeTrue();
});

it('allows a self-referred bill with no doctor', function () {
    $referral = BillingReferral::factory()->selfReferral()->create();

    expect($referral->doctor)->toBeNull()
        ->and($referral->is_self_referral)->toBeTrue();
});

it('allows only one referral row per bill', function () {
    $billing = Billing::factory()->create();

    BillingReferral::factory()->create(['billing_id' => $billing->id]);
    BillingReferral::factory()->create(['billing_id' => $billing->id]);
})->throws(QueryException::class);
