<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SeedRoleTemplates;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\Role;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    grantInBranch($this->admin, $this->branch, SharedPermission::RoleManage);
});

it('rejects a role template that mixes permissions from another module', function () {
    signInToBranch($this, $this->admin, $this->branch)
        ->postJson('/api/access/roles', [
            'name' => 'Front desk',
            'module' => 'billing',
            'permissions' => ['sh.billing.create', 'bb.case.view'],
        ])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['permissions.1']);
});

it('creates missing default templates once, keeping admin edits and not restoring deleted ones', function () {
    $created = app(SeedRoleTemplates::class)->handle();

    expect($created)->toContain('Cashier', 'Sample collection')
        ->and(Role::where('name', 'Sample collection')->value('module'))->toBe('blood-bank')
        ->and(Role::where('name', 'Cashier')->sole()->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['sh.billing.create', 'sh.billing.print', 'sh.billing.view', 'sh.patient.create', 'sh.patient.view']);

    Role::where('name', 'Cashier')->sole()->permissions()->detach();
    Role::where('name', 'Physician')->sole()->delete();

    expect(app(SeedRoleTemplates::class)->handle())->toBe([])
        ->and(Role::where('name', 'Cashier')->sole()->permissions()->count())->toBe(0)
        ->and(Role::where('name', 'Physician')->exists())->toBeFalse();
});
