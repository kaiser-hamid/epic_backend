<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    grantInBranch($this->user, $this->branch, SharedPermission::UserView);
});

it('returns 401 UNAUTHENTICATED without a session', function () {
    $this->withHeader('Referer', 'http://localhost')
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'UNAUTHENTICATED']);
});

it('returns 403 PASSWORD_CHANGE_REQUIRED until an admin-set password is replaced, but still serves me', function () {
    $this->user->forceFill(['must_change_password' => true])->save();

    signInToBranch($this, $this->user, $this->branch)->getJson('/api/access/users')
        ->assertForbidden()
        ->assertJson(['code' => 'PASSWORD_CHANGE_REQUIRED']);

    signInToBranch($this, $this->user, $this->branch)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.must_change_password', true);
});

it('clears the forced change once the user sets a new password', function () {
    $this->user->forceFill(['must_change_password' => true])->save();

    signInToBranch($this, $this->user, $this->branch)->putJson('/api/auth/password', [
        'current_password' => 'password',
        'password' => 'NewSecret2026',
        'password_confirmation' => 'NewSecret2026',
    ])->assertNoContent();

    expect($this->user->fresh()->must_change_password)->toBeFalse();
    signInToBranch($this, $this->user->fresh(), $this->branch)->getJson('/api/access/users')->assertOk();
});

it('returns 401 and ends the session when the user was deactivated mid-session', function () {
    $this->user->forceFill(['is_active' => false])->save();

    signInToBranch($this, $this->user, $this->branch)->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJson(['code' => 'UNAUTHENTICATED']);

    $this->assertGuest('web');
});

it('returns 401 BRANCH_INACTIVE when the session branch was deactivated', function () {
    $this->branch->update(['is_active' => false]);

    signInToBranch($this, $this->user, $this->branch)->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJson(['code' => 'BRANCH_INACTIVE']);
});

it('ends the session on logout', function () {
    signInToBranch($this, $this->user, $this->branch)->postJson('/api/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});
