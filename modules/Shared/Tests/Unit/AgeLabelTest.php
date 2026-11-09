<?php

use Modules\Shared\Patient\Services\AgeLabel;

it('shows only the largest unit of the age', function (?int $years, ?int $months, ?int $days, ?string $label) {
    expect(AgeLabel::format($years, $months, $days))->toBe($label);
})->with([
    'years, ignoring months and days' => [78, 4, 12, '78Y'],
    'months when under a year' => [0, 5, 20, '5M'],
    'days when under a month' => [0, 0, 22, '22D'],
    'years only (a donor)' => [34, null, null, '34Y'],
    'born today' => [0, 0, 0, '0D'],
    'unknown' => [null, null, null, null],
]);
