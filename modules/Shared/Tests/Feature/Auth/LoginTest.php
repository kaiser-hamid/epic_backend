<?php

use Illuminate\Testing\TestResponse;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost');
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['username' => 'cashier']);
    grantInBranch($this->user, $this->branch, SharedPermission::UserView);
});

function attemptLogin(string $password = 'password', ?int $branchId = null, string $username = 'cashier'): TestResponse
{
    return test()->postJson('/api/auth/login', [
        'username' => $username,
        'password' => $password,
        'branch_id' => $branchId ?? test()->branch->id,
    ]);
}

it('signs the user into the chosen branch and returns their permissions there', function () {
    attemptLogin()
        ->assertOk()
        ->assertJsonPath('data.user.username', 'cashier')
        ->assertJsonPath('data.branch.id', $this->branch->id)
        ->assertJsonPath('data.permissions', ['sh.user.view'])
        ->assertJsonPath('data.must_change_password', false)
        ->assertSessionHas('branch_id', $this->branch->id);

    $this->assertAuthenticatedAs($this->user);
    expect(AuditLog::where('action', AuditAction::Login)->where('actor_id', $this->user->id)->exists())->toBeTrue();
});

it('returns 401 INVALID_CREDENTIALS for a wrong password and for an unknown username alike', function () {
    attemptLogin('wrong-password')->assertUnauthorized()->assertJson(['code' => 'INVALID_CREDENTIALS']);
    attemptLogin(username: 'nobody')->assertUnauthorized()->assertJson(['code' => 'INVALID_CREDENTIALS']);

    $this->assertGuest();
});

it('locks the account with 423 on the fifth consecutive failure', function () {
    foreach (range(1, 4) as $attempt) {
        attemptLogin('wrong-password')->assertJson(['code' => 'INVALID_CREDENTIALS']);
    }

    attemptLogin('wrong-password')
        ->assertStatus(423)
        ->assertJson(['code' => 'ACCOUNT_LOCKED'])
        ->assertJsonStructure(['locked_until']);

    expect(AuditLog::where('action', AuditAction::AccountLocked)->count())->toBe(1);
});

it('rejects the correct password while locked and accepts it once the lock expires', function () {
    $this->user->forceFill(['locked_until' => now()->addMinutes(15)])->save();

    attemptLogin()->assertStatus(423)->assertJson(['code' => 'ACCOUNT_LOCKED']);

    $this->travel(16)->minutes();

    attemptLogin()->assertOk();
});

it('returns 403 ACCOUNT_INACTIVE for a deactivated user with the correct password', function () {
    $this->user->forceFill(['is_active' => false])->save();

    attemptLogin()->assertForbidden()->assertJson(['code' => 'ACCOUNT_INACTIVE']);
});

it('returns 403 BRANCH_ACCESS_DENIED for a branch where the user holds no permission', function () {
    $otherBranch = Branch::factory()->create();

    attemptLogin(branchId: $otherBranch->id)->assertForbidden()->assertJson(['code' => 'BRANCH_ACCESS_DENIED']);

    $this->assertGuest();
});

it('returns 422 VALIDATION_FAILED for an inactive branch', function () {
    $this->branch->update(['is_active' => false]);

    attemptLogin()
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['branch_id' => 'The selected branch is invalid.']);
});

it('lets a super admin sign into any active branch without grants', function () {
    $admin = User::factory()->superAdmin()->create(['username' => 'it-admin']);
    $otherBranch = Branch::factory()->create();

    attemptLogin(branchId: $otherBranch->id, username: 'it-admin')
        ->assertOk()
        ->assertJsonPath('data.is_super_admin', true)
        ->assertJsonPath('data.branch.id', $otherBranch->id);

    $this->assertAuthenticatedAs($admin);
});

it('returns 429 TOO_MANY_ATTEMPTS after five attempts per username and IP in a minute', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin(username: 'nobody');
    }

    attemptLogin(username: 'nobody')
        ->assertTooManyRequests()
        ->assertJson(['code' => 'TOO_MANY_ATTEMPTS'])
        ->assertHeader('Retry-After');
});

it('returns 422 VALIDATION_FAILED with field errors for an empty payload', function () {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['username', 'password', 'branch_id']);
});
