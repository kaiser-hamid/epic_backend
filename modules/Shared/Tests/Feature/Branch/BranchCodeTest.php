<?php

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

it('accepts only a two-letter uppercase branch code, since it prefixes every invoice number', function (string $code) {
    $superAdmin = User::factory()->superAdmin()->create();

    signInToBranch($this, $superAdmin, Branch::factory()->create())
        ->postJson('/api/access/branches', ['code' => $code, 'name' => 'Mirpur'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'VALIDATION_FAILED'])
        ->assertJsonValidationErrors(['code']);
})->with([
    'three letters' => 'MIR',
    'one letter' => 'M',
    'lowercase' => 'mi',
    'digits' => 'M1',
]);
