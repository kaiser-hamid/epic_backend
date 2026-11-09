<?php

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\TestCatalog\Models\TestItem;
use Tests\TestCase;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->cashier = User::factory()->create();
    grantInBranch($this->cashier, $this->branch, SharedPermission::BillingCreate);
});

/**
 * Posts a bill under the "General Patient" type with a fresh idempotency key, unless the payload sets them.
 *
 * @param  array<string, mixed>  $payload
 */
function createInvoice(TestCase $test, array $payload): TestResponse
{
    return signInToBranch($test, $test->cashier, $test->branch)->postJson('/api/billing/invoices', [
        'idempotency_key' => (string) Str::uuid(),
        'patient_type_id' => PatientType::where('code', PatientType::GENERAL)->value('id'),
        ...$payload,
    ]);
}

it('registers a new patient and bills catalog rates, numbering the invoice per branch', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $cbc = TestItem::factory()->create(['rate' => 50000]);
    $xray = TestItem::factory()->create(['rate' => 120000]);
    $doctor = Doctor::factory()->create();

    $response = createInvoice($this, [
        'patient' => ['name' => 'Rahim Uddin', 'age_years' => 40, 'gender' => 'male', 'phone' => '01711000000'],
        'items' => [
            ['test_item_id' => $cbc->id, 'quantity' => 2, 'rate' => 1],
            ['test_item_id' => $xray->id],
        ],
        'referral' => ['doctor_id' => $doctor->id, 'remark' => 'Urgent'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.invoice_no', "{$this->branch->code}2610280001")
        ->assertJsonPath('data.amount', 220000)
        ->assertJsonPath('data.patient.name', 'Rahim Uddin')
        ->assertJsonPath('data.referral.doctor.id', $doctor->id)
        ->assertJsonPath('data.items.0.rate', 50000)
        ->assertJsonPath('data.items.0.amount', 100000);

    $billing = Billing::sole();
    expect($billing->branch_id)->toBe($this->branch->id)
        ->and($billing->patient->created_by)->toBe($this->cashier->id)
        ->and(AuditLog::where('action', AuditAction::PatientRegistered)->count())->toBe(1)
        ->and(AuditLog::where('action', AuditAction::InvoiceCreated)->sole()->new_values['amount'])->toBe(220000);

    createInvoice($this, [
        'patient_id' => $billing->patient_master_id,
        'items' => [['test_item_id' => $cbc->id]],
        'referral' => ['is_self_referral' => true],
    ])->assertJsonPath('data.invoice_no', "{$this->branch->code}2610280002");
});

it('keeps a separate invoice counter for each branch', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $otherBranch = Branch::factory()->create();
    grantInBranch($this->cashier, $otherBranch, SharedPermission::BillingCreate);
    $payload = fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ];

    createInvoice($this, $payload())->assertJsonPath('data.invoice_no', "{$this->branch->code}2610280001");

    signInToBranch($this, $this->cashier, $otherBranch)
        ->postJson('/api/billing/invoices', [
            'idempotency_key' => (string) Str::uuid(),
            'patient_type_id' => PatientType::where('code', PatientType::GENERAL)->value('id'),
            ...$payload(),
        ])
        ->assertJsonPath('data.invoice_no', "{$otherBranch->code}2610280001");
});

it('restarts the branch\'s invoice counter every day', function () {
    $payload = fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ];

    $this->travelTo(now()->setDate(2026, 10, 28)->setTime(23, 59));
    createInvoice($this, $payload());
    createInvoice($this, $payload())->assertJsonPath('data.invoice_no', "{$this->branch->code}2610280002");

    $this->travelTo(now()->setDate(2026, 10, 29)->setTime(0, 1));
    createInvoice($this, $payload())->assertJsonPath('data.invoice_no', "{$this->branch->code}2610290001");
});

it('bills a returning patient without registering them again', function () {
    $patient = PatientMaster::factory()->create();

    createInvoice($this, [
        'patient_id' => $patient->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ])->assertCreated()->assertJsonPath('data.patient.id', $patient->id);

    expect(PatientMaster::count())->toBe(1);
});

it('returns 422 VALIDATION_FAILED for an invalid bill', function (Closure $payload, string $field) {
    createInvoice($this, $payload())
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors([$field]);

    expect(Billing::count())->toBe(0);
})->with([
    'neither patient nor patient_id' => [fn () => [
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ], 'patient_id'],
    'both patient and patient_id' => [fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'patient' => ['name' => 'Karim', 'age_years' => 30],
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ], 'patient_id'],
    'new patient without age or date of birth' => [fn () => [
        'patient' => ['name' => 'Karim'],
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ], 'patient.age_years'],
    'no items' => [fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [],
        'referral' => ['is_self_referral' => true],
    ], 'items'],
    'inactive test item' => [fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create(['is_active' => false])->id]],
        'referral' => ['is_self_referral' => true],
    ], 'items.0.test_item_id'],
    'same test item twice' => [function () {
        $testItem = TestItem::factory()->create();

        return [
            'patient_id' => PatientMaster::factory()->create()->id,
            'items' => [['test_item_id' => $testItem->id], ['test_item_id' => $testItem->id]],
            'referral' => ['is_self_referral' => true],
        ];
    }, 'items.0.test_item_id'],
    'neither doctor nor self-referral' => [fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['remark' => 'Walk-in'],
    ], 'referral.doctor_id'],
    'doctor on a self-referral' => [fn () => [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true, 'doctor_id' => Doctor::factory()->create()->id],
    ], 'referral.doctor_id'],
]);

it('returns 403 FORBIDDEN without the billing permission', function () {
    $receptionist = User::factory()->create();
    grantInBranch($receptionist, $this->branch, SharedPermission::BillingView);

    signInToBranch($this, $receptionist, $this->branch)
        ->postJson('/api/billing/invoices', [])
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('refuses a bill mixing Blood Bank and other items and saves nothing', function () {
    createInvoice($this, [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [
            ['test_item_id' => TestItem::factory()->create()->id],
            ['test_item_id' => TestItem::factory()->bloodBank()->create()->id],
        ],
        'referral' => ['is_self_referral' => true],
    ])->assertUnprocessable()->assertJson(['code' => 'INVOICE_CATEGORY_MIXED']);

    expect(Billing::count())->toBe(0);
});

it('returns the bill already saved when the same form is submitted again, and refuses another user\'s key', function () {
    $payload = [
        'idempotency_key' => (string) Str::uuid(),
        'patient' => ['name' => 'Rahim Uddin', 'age_years' => 40],
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ];

    $first = createInvoice($this, $payload)->assertCreated();
    createInvoice($this, $payload)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

    expect(Billing::count())->toBe(1)->and(PatientMaster::count())->toBe(1);

    $otherCashier = User::factory()->create();
    grantInBranch($otherCashier, $this->branch, SharedPermission::BillingCreate);

    signInToBranch($this, $otherCashier, $this->branch)
        ->postJson('/api/billing/invoices', [...$payload, 'patient_type_id' => PatientType::where('code', PatientType::GENERAL)->value('id')])
        ->assertConflict()
        ->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);
});

it('uses a typed vendor invoice number, which must be unused', function () {
    $payload = fn () => [
        'invoice_no' => 'VND12345',
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
    ];

    createInvoice($this, $payload())->assertCreated()->assertJsonPath('data.invoice_no', 'VND12345');
    createInvoice($this, $payload())->assertUnprocessable()->assertJson(['code' => 'INVOICE_NO_TAKEN']);
});

it('refuses category details on a bill whose category takes none', function () {
    createInvoice($this, [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
        'category_details' => ['care_type' => 'daycare'],
    ])->assertUnprocessable()->assertJsonValidationErrors('category_details');

    expect(Billing::count())->toBe(0);
});

it('applies the patient type\'s discount policy and keeps a snapshot of it on the bill', function () {
    $general = PatientType::where('code', PatientType::GENERAL)->sole();
    $policy = $general->discountPolicies()->sole();
    $cbc = TestItem::factory()->create(['rate' => 136300]);
    $consumable = TestItem::factory()->discountPercent(0)->create(['rate' => 10000]);

    createInvoice($this, [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => $cbc->id], ['test_item_id' => $consumable->id]],
        'referral' => ['is_self_referral' => true],
        'patient_type_id' => $general->id,
        'discount_policy_id' => $policy->id,
    ])->assertCreated()
        ->assertJsonPath('data.patient_type.code', 'GENERAL')
        ->assertJsonPath('data.discount.name', 'General 25%')
        ->assertJsonPath('data.subtotal_amount', 146300)
        ->assertJsonPath('data.discount_amount', 34075)
        ->assertJsonPath('data.rounding_amount', 775)
        ->assertJsonPath('data.amount', 113000)
        ->assertJsonPath('data.items.0.discount_percent', 25)
        ->assertJsonPath('data.items.1.discount_amount', 0);

    $policy->update(['rate_bp' => 5000, 'name' => 'General 50%']);

    expect(Billing::sole()->discounts()->sole()->only(['name', 'rate_bp', 'amount']))
        ->toBe(['name' => 'General 25%', 'rate_bp' => 2500, 'amount' => 34075]);
});

it('refuses a discount policy that does not belong to the patient type', function () {
    createInvoice($this, [
        'patient_id' => PatientMaster::factory()->create()->id,
        'items' => [['test_item_id' => TestItem::factory()->create()->id]],
        'referral' => ['is_self_referral' => true],
        'discount_policy_id' => DiscountPolicy::factory()->create()->id,
    ])->assertUnprocessable()->assertJson(['code' => 'DISCOUNT_POLICY_NOT_ALLOWED']);

    expect(Billing::count())->toBe(0);
});

it('quotes the totals of a bill being filled in without saving it', function () {
    $general = PatientType::where('code', PatientType::GENERAL)->sole();

    signInToBranch($this, $this->cashier, $this->branch)
        ->postJson('/api/billing/invoices/quote', [
            'items' => [['test_item_id' => TestItem::factory()->create(['rate' => 136300])->id]],
            'patient_type_id' => $general->id,
            'discount_policy_id' => $general->discountPolicies()->sole()->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.subtotal_amount', 136300)
        ->assertJsonPath('data.discount_amount', 34075)
        ->assertJsonPath('data.amount', 103000);

    expect(Billing::count())->toBe(0);
});
