<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Shared\Auth\Actions\RevokeOtherSessions;
use Modules\Shared\Auth\Actions\SetSuperAdmin;
use Modules\Shared\Auth\Exceptions\LastSuperAdmin;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    $this->staff = User::factory()->create();
    grantInBranch($this->admin, $this->branch, SharedPermission::UserResetPassword, SharedPermission::UserDeactivate);
});

function storeSession(User $user, string $id): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
}

it('sets a temporary password, forces a change and signs the user out everywhere on reset', function () {
    $phone = str_repeat('p', 40);
    storeSession($this->staff, $phone);
    $this->staff->forceFill(['locked_until' => now()->addMinutes(5)])->save();

    signInToBranch($this, $this->admin, $this->branch)
        ->postJson("/api/access/users/{$this->staff->id}/reset-password", ['password' => 'TempPass2026'])
        ->assertNoContent();

    $staff = $this->staff->fresh();
    expect(Hash::check('TempPass2026', $staff->password))->toBeTrue()
        ->and($staff->must_change_password)->toBeTrue()
        ->and($staff->isLocked())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse();
});

it('rejects a weak temporary password with 422', function () {
    signInToBranch($this, $this->admin, $this->branch)
        ->postJson("/api/access/users/{$this->staff->id}/reset-password", ['password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 403 SUPER_ADMIN_PROTECTED when an admin deactivates a super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    signInToBranch($this, $this->admin, $this->branch)
        ->postJson("/api/access/users/{$superAdmin->id}/deactivate")
        ->assertForbidden()
        ->assertJson(['code' => 'SUPER_ADMIN_PROTECTED']);

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

it('returns 422 CANNOT_CHANGE_OWN_ACCOUNT when an admin deactivates themselves', function () {
    signInToBranch($this, $this->admin, $this->branch)
        ->postJson("/api/access/users/{$this->admin->id}/deactivate")
        ->assertUnprocessable()
        ->assertJson(['code' => 'CANNOT_CHANGE_OWN_ACCOUNT']);
});

it('signs out every other device but keeps the current session', function () {
    [$laptop, $phone] = [str_repeat('l', 40), str_repeat('p', 40)];
    storeSession($this->staff, $laptop);
    storeSession($this->staff, $phone);
    $session = app('session')->driver();
    $session->setId($laptop);
    request()->setLaravelSession($session);

    $revoked = app(RevokeOtherSessions::class)->handle($this->staff);

    expect($revoked)->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $this->staff->id)->pluck('id')->all())->toBe([$laptop]);
});

it('refuses to revoke the last active super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    app(SetSuperAdmin::class)->handle($superAdmin, false);
})->throws(LastSuperAdmin::class);

it('grants super admin through the console command', function () {
    $this->artisan('auth:super-admin', ['username' => $this->staff->username])->assertSuccessful();

    expect($this->staff->fresh()->is_super_admin)->toBeTrue();
});

it('ignores is_super_admin sent through the user update API', function () {
    grantInBranch($this->admin, $this->branch, SharedPermission::UserUpdate);

    signInToBranch($this, $this->admin, $this->branch)
        ->patchJson("/api/access/users/{$this->admin->id}", ['name' => 'Renamed', 'is_super_admin' => true])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.is_super_admin', false);
});
