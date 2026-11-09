<?php

use Modules\Shared\Billing\Data\BillLineInput;
use Modules\Shared\Billing\Services\InvoiceCalculator;

it('discounts each line, then drops the paisa and rounds the payable up to the next 10 taka', function () {
    $totals = (new InvoiceCalculator)->calculate([new BillLineInput(1, 1, 136300)], 2500);

    expect($totals->subtotalAmount)->toBe(136300)
        ->and($totals->discountAmount)->toBe(34075)
        ->and($totals->roundingAmount)->toBe(775)
        ->and($totals->amount)->toBe(103000);
});

it('rounds the payable on every bill, down when only paisa are left over a round figure', function (int $rate, ?int $policyRateBp, int $payable) {
    expect((new InvoiceCalculator)->calculate([new BillLineInput(1, 1, $rate)], $policyRateBp)->amount)->toBe($payable);
})->with([
    'no discount, Tk 1,022' => [102200, null, 103000],
    'Tk 1,020.50 left' => [102050, null, 102000],
    'already a round figure' => [102000, null, 102000],
]);

it('gives a line the item\'s own rate instead of the policy rate, 0 meaning never discounted', function () {
    $totals = (new InvoiceCalculator)->calculate([
        new BillLineInput(1, 2, 50000),
        new BillLineInput(2, 1, 100000, itemDiscountRateBp: 1000),
        new BillLineInput(3, 1, 60000, itemDiscountRateBp: 0),
    ], 2500);

    expect(array_map(fn ($line) => [$line->discountRateBp, $line->discountAmount], $totals->lines))
        ->toBe([[2500, 25000], [1000, 10000], [0, 0]])
        ->and($totals->discountAmount)->toBe(35000)
        ->and($totals->amount)->toBe(225000);
});

it('ignores the item\'s own rate when the bill has no discount policy', function () {
    $totals = (new InvoiceCalculator)->calculate([new BillLineInput(1, 1, 100000, itemDiscountRateBp: 1000)], null);

    expect($totals->lines[0]->discountRateBp)->toBeNull()
        ->and($totals->discountAmount)->toBe(0);
});
