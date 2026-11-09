<?php

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\TestCatalog\Models\TestItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->cashier = User::factory()->create();
    grantInBranch($this->cashier, $this->branch, SharedPermission::BillingCreate);

    $this->pcv = ComponentType::factory()->create(['code' => 'PCV']);
    $this->fp = ComponentType::factory()->create(['code' => 'FP']);
    $this->crossMatching = TestItem::factory()->bloodBank()->create();

    $this->bill = fn (array $payload): TestResponse => signInToBranch($this, $this->cashier, $this->branch)
        ->postJson('/api/billing/invoices', [
            'idempotency_key' => (string) Str::uuid(),
            'patient_type_id' => PatientType::where('code', PatientType::GENERAL)->value('id'),
            'delivery_at' => now()->toIso8601String(),
            'paid_amount' => 0,
            'patient_id' => PatientMaster::factory()->create()->id,
            'referral' => ['is_self_referral' => true],
            ...$payload,
        ]);
});

it('opens one Blood Bank case from a Blood Bank bill, requesting the billed components', function () {
    $this->travelTo(now()->setDate(2026, 11, 9));

    ($this->bill)([
        'items' => [
            ['test_item_id' => $this->pcv->test_item_id, 'quantity' => 2],
            ['test_item_id' => $this->fp->test_item_id],
            ['test_item_id' => $this->crossMatching->id],
        ],
        'category_details' => ['care_type' => 'daycare'],
    ])->assertCreated()->assertJsonPath('data.category', 'blood_bank');

    $billing = Billing::sole();
    $case = PatientCase::with('components')->sole();

    expect($case->case_no)->toBe('BC2611000001')
        ->and($case->billing_id)->toBe($billing->id)
        ->and($case->patient_master_id)->toBe($billing->patient_master_id)
        ->and($case->branch_id)->toBe($this->branch->id)
        ->and($case->care_type->value)->toBe('daycare')
        ->and($case->components->map->only(['component_type_id', 'test_item_id', 'quantity'])->all())->toBe([
            ['component_type_id' => $this->pcv->id, 'test_item_id' => $this->pcv->test_item_id, 'quantity' => 2],
            ['component_type_id' => $this->fp->id, 'test_item_id' => $this->fp->test_item_id, 'quantity' => 1],
        ])
        ->and(AuditLog::where('action', BloodBankAuditAction::CaseOpened)->sole()->new_values['invoice_no'])->toBe($billing->invoice_no);
});

it('needs "Care of" on a Blood Bank bill and saves nothing without it', function () {
    ($this->bill)(['items' => [['test_item_id' => $this->pcv->test_item_id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_details.care_type');

    expect(Billing::count())->toBe(0)->and(PatientCase::count())->toBe(0);
});

it('opens no second case when the same bill form is submitted again', function () {
    $payload = [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['test_item_id' => $this->pcv->test_item_id]],
        'category_details' => ['care_type' => 'out'],
    ];

    ($this->bill)($payload)->assertCreated();
    ($this->bill)($payload)->assertOk();

    expect(Billing::count())->toBe(1)->and(PatientCase::count())->toBe(1);
});

it('opens no case for a general bill, such as the additional donor charge', function () {
    ($this->bill)(['items' => [['test_item_id' => TestItem::where('code', 'BB-ADDL-DONOR')->value('id')]]])
        ->assertCreated()
        ->assertJsonPath('data.category', 'general');

    expect(PatientCase::count())->toBe(0);
});

it('leaves no bill behind when the case cannot be opened', function () {
    $this->travelTo(now()->setDate(2026, 11, 9));
    PatientCase::factory()->create(['case_no' => 'BC2611000001']);

    ($this->bill)([
        'items' => [['test_item_id' => $this->pcv->test_item_id]],
        'category_details' => ['care_type' => 'out'],
    ])->assertServerError();

    expect(Billing::where('category', 'blood_bank')->whereNotNull('idempotency_key')->exists())->toBeFalse()
        ->and(PatientCase::count())->toBe(1);
});
