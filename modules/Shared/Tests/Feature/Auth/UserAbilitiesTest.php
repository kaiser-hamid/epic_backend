<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['name' => 'A Admin']);
    grantInBranch($this->admin, $this->branch, SharedPermission::UserView, SharedPermission::UserUpdate, SharedPermission::UserDeactivate);
});

it('offers only the account actions the admin may take on each listed user', function () {
    $staff = User::factory()->create(['name' => 'B Staff']);
    $superAdmin = User::factory()->superAdmin()->create(['name' => 'C Super']);

    $rows = signInToBranch($this, $this->admin, $this->branch)
        ->getJson('/api/access/users')
        ->assertOk()
        ->collect('data')
        ->keyBy('id');

    expect($rows[$staff->id]['abilities'])->toBe(['update' => true, 'change_status' => true, 'reset_password' => false, 'assign_permissions' => false])
        ->and($rows[$this->admin->id]['abilities'])->toBe(['update' => true, 'change_status' => false, 'reset_password' => false, 'assign_permissions' => false])
        ->and($rows[$superAdmin->id]['abilities'])->toBe(['update' => false, 'change_status' => false, 'reset_password' => false, 'assign_permissions' => false]);
});
