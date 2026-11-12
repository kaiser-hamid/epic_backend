<?php

use Illuminate\Support\Facades\Hash;
use Modules\Shared\Auth\Enums\Designation;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Enums\SharedPermission;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    grantInBranch($this->admin, $this->branch, SharedPermission::UserCreate, SharedPermission::UserUpdate, SharedPermission::UserResetPassword);
});

function newUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Rina Akter',
        'username' => fake()->unique()->bothify('user_####'),
        'password' => '123456789012',
        'designation' => Designation::Cashier->value,
        'phone' => '01712345678',
        ...$overrides,
    ];
}

it('numbers new employees E + yyMM + a monthly counter and ignores an employee id sent by the client', function () {
    $this->travelTo(now()->setDate(2026, 11, 15));

    $first = signInToBranch($this, $this->admin, $this->branch)
        ->postJson('/api/access/users', newUserPayload(['employee_id' => 'HACK-1']))
        ->assertCreated()
        ->assertJsonPath('data.employee_id', 'E2611001')
        ->assertJsonPath('data.designation', 'cashier');

    $this->postJson('/api/access/users', newUserPayload())->assertJsonPath('data.employee_id', 'E2611002');

    $this->travelTo(now()->setDate(2026, 12, 1));
    $this->postJson('/api/access/users', newUserPayload())->assertJsonPath('data.employee_id', 'E2612001');

    expect(Hash::check('123456789012', User::find($first->json('data.id'))->password))->toBeTrue();
});

it('accepts letter-only, digit-only and mixed passwords but rejects special characters', function (string $password, bool $valid) {
    $response = signInToBranch($this, $this->admin, $this->branch)
        ->postJson('/api/access/users', newUserPayload(['password' => $password]));

    $valid ? $response->assertCreated() : $response->assertJsonValidationErrors(['password']);
})->with([
    'letters' => ['abcdef', true],
    'capitalised letters' => ['Abcdef', true],
    'digits' => ['123456', true],
    'mixed' => ['123abc', true],
    'mixed case' => ['123Abc', true],
    'too short' => ['abc12', false],
    'special' => ['Abc12345!@', false],
]);

it('requires a designation on a new user', function (array $overrides) {
    signInToBranch($this, $this->admin, $this->branch)
        ->postJson('/api/access/users', newUserPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['designation']);
})->with([
    'missing' => [['designation' => null]],
    'empty' => [['designation' => '']],
    'unknown' => [['designation' => 'astronaut']],
]);

it('does not clear the designation on a profile update', function () {
    $staff = User::factory()->create(['designation' => Designation::Nurse]);

    signInToBranch($this, $this->admin, $this->branch)
        ->patchJson("/api/access/users/{$staff->id}", ['designation' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['designation']);

    expect($staff->fresh()->designation)->toBe(Designation::Nurse);
});

it('rejects a phone number that is not 01 followed by nine digits', function (string $phone) {
    signInToBranch($this, $this->admin, $this->branch)
        ->postJson('/api/access/users', newUserPayload(['phone' => $phone]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
})->with(['0171234567', '017123456789', '02712345678', '+8801712345678']);

it('rejects special characters in a reset password', function () {
    $staff = User::factory()->create();

    signInToBranch($this, $this->admin, $this->branch)
        ->postJson("/api/access/users/{$staff->id}/reset-password", ['password' => 'Temp#Pass2026'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('keeps the username and employee id when they are sent with a profile update', function () {
    $staff = User::factory()->create(['username' => 'rina', 'employee_id' => 'E2611005']);

    signInToBranch($this, $this->admin, $this->branch)
        ->patchJson("/api/access/users/{$staff->id}", ['username' => 'changed', 'employee_id' => 'X1', 'designation' => 'nurse'])
        ->assertOk()
        ->assertJsonPath('data.username', 'rina')
        ->assertJsonPath('data.employee_id', 'E2611005')
        ->assertJsonPath('data.designation', 'nurse');
});
