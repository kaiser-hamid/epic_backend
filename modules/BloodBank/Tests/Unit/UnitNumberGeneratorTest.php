<?php

use Modules\BloodBank\Services\UnitNumberGenerator;

it('numbers a unit as the bag number under the component prefix', function () {
    expect((new UnitNumberGenerator)->forUnit('B2611020003', 'BT'))->toBe('BT2611020003');
});

it('appends the split number for a component divided into several bags', function () {
    expect((new UnitNumberGenerator)->forUnit('B2611020003', 'BP', 2))->toBe('BP2611020003-2');
});
