<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
});

it('lists only the active branches where the user holds a permission', function () {
    $user = User::factory()->create(['username' => 'cashier']);
    $granted = Branch::factory()->create(['name' => 'Dhanmondi']);
    $deactivated = Branch::factory()->create(['name' => 'Uttara']);
    Branch::factory()->create(['name' => 'Mirpur']);
    grantInBranch($user, $granted, SharedPermission::UserView);
    grantInBranch($user, $deactivated, SharedPermission::UserView);
    $deactivated->update(['is_active' => false]);

    $this->postJson('/api/auth/login-branches', ['username' => 'cashier'])
        ->assertOk()
        ->assertExactJson(['data' => [['id' => $granted->id, 'code' => $granted->code, 'name' => 'Dhanmondi']]]);
});

it('lists every active branch for a super admin', function () {
    User::factory()->superAdmin()->create(['username' => 'it-admin']);
    Branch::factory()->count(2)->create();
    Branch::factory()->inactive()->create();

    $this->postJson('/api/auth/login-branches', ['username' => 'it-admin'])
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns the same empty list for unknown, inactive and locked accounts', function (string $username) {
    $branch = Branch::factory()->create();
    $inactive = User::factory()->inactive()->create(['username' => 'left-company']);
    $locked = User::factory()->create(['username' => 'locked-out', 'locked_until' => now()->addMinutes(10)]);
    grantInBranch($inactive, $branch, SharedPermission::UserView);
    grantInBranch($locked, $branch, SharedPermission::UserView);

    $this->postJson('/api/auth/login-branches', ['username' => $username])
        ->assertOk()
        ->assertExactJson(['data' => []]);
})->with(['nobody', 'left-company', 'locked-out']);
