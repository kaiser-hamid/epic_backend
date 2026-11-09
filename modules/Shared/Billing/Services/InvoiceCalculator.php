<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Billing\Data\BillLineInput;
use Modules\Shared\Billing\Data\InvoiceTotals;
use Modules\Shared\Billing\Data\PricedLine;

/**
 * The bill arithmetic, in integer paisa.
 *
 * With a discount policy, every line is discounted at the item's own rate when it has one (0 = never discounted),
 * else at the policy's rate; without a policy nothing is discounted, whatever the item says. Each line's discount
 * is rounded half up to the paisa. The payable then drops its paisa and is rounded up to the next 10 taka
 * (1,022.25 → 1,030; 1,020.50 → 1,020), on every bill.
 */
final class InvoiceCalculator
{
    private const int BASIS_POINTS = 10_000;

    private const int PAISA_PER_TAKA = 100;

    private const int ROUND_TO_TAKA = 10;

    /**
     * @param  list<BillLineInput>  $lines
     */
    public function calculate(array $lines, ?int $policyRateBp): InvoiceTotals
    {
        $pricedLines = array_map(fn (BillLineInput $line): PricedLine => $this->line($line, $policyRateBp), $lines);

        $subtotal = array_sum(array_map(fn (PricedLine $line): int => $line->amount, $pricedLines));
        $discount = array_sum(array_map(fn (PricedLine $line): int => $line->discountAmount, $pricedLines));
        $payable = $this->payable($subtotal - $discount);

        return new InvoiceTotals(
            lines: $pricedLines,
            subtotalAmount: $subtotal,
            discountAmount: $discount,
            roundingAmount: $payable - ($subtotal - $discount),
            amount: $payable,
        );
    }

    private function line(BillLineInput $line, ?int $policyRateBp): PricedLine
    {
        $amount = $line->rate * $line->quantity;
        $rateBp = $policyRateBp === null ? null : ($line->itemDiscountRateBp ?? $policyRateBp);
        $discount = $rateBp === null ? 0 : intdiv($amount * $rateBp + self::BASIS_POINTS / 2, self::BASIS_POINTS);

        return new PricedLine(
            testItemId: $line->testItemId,
            quantity: $line->quantity,
            rate: $line->rate,
            amount: $amount,
            discountRateBp: $rateBp,
            discountAmount: $discount,
            netAmount: $amount - $discount,
        );
    }

    private function payable(int $net): int
    {
        $taka = intdiv($net, self::PAISA_PER_TAKA);

        return intdiv($taka + self::ROUND_TO_TAKA - 1, self::ROUND_TO_TAKA) * self::ROUND_TO_TAKA * self::PAISA_PER_TAKA;
    }
}
