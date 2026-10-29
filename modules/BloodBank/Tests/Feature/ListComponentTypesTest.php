<?php

use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

it('lists only active component types by name with id, code and name', function () {
    $platelet = ComponentType::factory()->create(['code' => 'PLT', 'name' => 'Platelet', 'is_active' => true]);
    $plasma = ComponentType::factory()->create(['code' => 'FP', 'name' => 'Fresh Plasma', 'is_active' => true]);
    ComponentType::factory()->create(['is_active' => false]);

    signInToBranch($this, User::factory()->create(), Branch::factory()->create())
        ->getJson('/api/blood-bank/component-types')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $plasma->id, 'code' => 'FP', 'name' => 'Fresh Plasma'],
            ['id' => $platelet->id, 'code' => 'PLT', 'name' => 'Platelet'],
        ]]);
});
