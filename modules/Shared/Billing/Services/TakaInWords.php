<?php

namespace Modules\Shared\Billing\Services;

/**
 * An amount in words as printed on a bill, counted the Bangladeshi way (thousand, lakh, crore):
 * 712000 paisa → "Taka Seven Thousand One Hundred Twenty Only".
 */
final class TakaInWords
{
    private const array ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const array TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /**
     * Largest unit first; a crore count may itself run into lakhs, so it is spelled out recursively.
     */
    private const array UNITS = [10_000_000 => 'Crore', 100_000 => 'Lakh', 1_000 => 'Thousand', 100 => 'Hundred'];

    public function format(int $paisa): string
    {
        $taka = intdiv($paisa, 100);
        $remainder = $paisa % 100;

        $text = 'Taka '.($taka === 0 ? 'Zero' : $this->words($taka));

        if ($remainder > 0) {
            $text .= ' and '.$this->belowHundred($remainder).' Paisa';
        }

        return $text.' Only';
    }

    private function words(int $number): string
    {
        $parts = [];

        foreach (self::UNITS as $unit => $name) {
            if ($number >= $unit) {
                $count = intdiv($number, $unit);
                $parts[] = ($count >= 100 ? $this->words($count) : $this->belowHundred($count)).' '.$name;
                $number %= $unit;
            }
        }

        if ($number > 0) {
            $parts[] = $this->belowHundred($number);
        }

        return implode(' ', $parts);
    }

    private function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        return trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
    }
}
