<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->staff, $this->branch, SharedPermission::BillingView);
});

it('lists only the session branch\'s invoices, filtered by patient phone', function () {
    $patient = PatientMaster::factory()->create(['phone' => '01811223344']);
    $mine = Billing::factory()->for($patient, 'patient')->create(['branch_id' => $this->branch->id]);
    Billing::factory()->create(['branch_id' => $this->branch->id]);
    Billing::factory()->for($patient, 'patient')->create(['branch_id' => $this->otherBranch->id]);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/billing/invoices?filter[search]=01811223344')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('pages with the shadcn data table meta and no links', function () {
    Billing::factory()->count(5)->create(['branch_id' => $this->branch->id]);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/billing/invoices?page=2&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta', ['page_index' => 1, 'page_size' => 2, 'page_count' => 3, 'row_count' => 5])
        ->assertJsonMissingPath('links');
});

it('returns 403 FORBIDDEN for another branch\'s invoice even with the permission there', function () {
    grantInBranch($this->staff, $this->otherBranch, SharedPermission::BillingView);
    $foreign = Billing::factory()->create(['branch_id' => $this->otherBranch->id]);

    signInToBranch($this, $this->staff, $this->branch)
        ->getJson("/api/billing/invoices/{$foreign->id}")
        ->assertForbidden()
        ->assertJson(['code' => 'FORBIDDEN']);
});

it('returns 400 INVALID_QUERY for a filter that is not allowed', function () {
    signInToBranch($this, $this->staff, $this->branch)
        ->getJson('/api/billing/invoices?filter[branch_id]=1')
        ->assertBadRequest()
        ->assertJson(['code' => 'INVALID_QUERY']);
});
