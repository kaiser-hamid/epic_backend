<?php

namespace Modules\Shared\Printing\Contracts;

/**
 * Fills a label printer command template (GoDEX EZPL, designed in GoLabel, under `resources/label`) for any module.
 * The result is sent as-is to the printer by the frontend's print service. Recording the print is the caller's job
 * (PrintLogger).
 */
interface LabelRenderer
{
    /**
     * @param  string  $template  file name under `resources/label`, e.g. `bb_barcode_label.ezpx`
     * @param  array<string, string>  $values  keyed by template variable (`V00`), replacing each `^V00` in the fields
     * @param  int  $copies  copies the printer makes of the label (the template's `^C` setup line); the design is unchanged
     * @return string the printer command, lines ending in CRLF
     */
    public function render(string $template, array $values, int $copies = 1): string;
}
