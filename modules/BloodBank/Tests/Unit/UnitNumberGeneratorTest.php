<?php

use Modules\BloodBank\Services\UnitNumberGenerator;

it('uses the bag number for the whole-blood unit', function () {
    expect((new UnitNumberGenerator)->forUnit('BB-B-000123'))->toBe('BB-B-000123');
});

it('appends the component code for a component', function () {
    expect((new UnitNumberGenerator)->forUnit('BB-B-000123', 'PCV'))->toBe('BB-B-000123-PCV');
});

it('appends the split number for a split component', function () {
    expect((new UnitNumberGenerator)->forUnit('BB-B-000123', 'PLT', 2))->toBe('BB-B-000123-PLT-2');
});
