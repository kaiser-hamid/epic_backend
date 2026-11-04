<?php

namespace Modules\Shared\Printing\Services;

use Illuminate\Filesystem\Filesystem;
use Modules\Shared\Printing\Contracts\LabelRenderer;

/**
 * Label templates are GoLabel EZPL exports under `resources/label`, for a 203 dpi (8 dots/mm) GoDEX printer. Each
 * `^Vxx` is replaced with its value, cut to the variable's declared length (`Vxx,length,,jc28`); the variables are
 * `UNPROMPT`, so the printer never waits for input.
 *
 * The printer only justifies variables it fills itself, so centring (`jc..`) is done here: a text field
 * (`A{font},x,y,...,^Vxx`, not rotated) of a centred variable gets the x that centres its value on the label width
 * (`^W`, mm), measured with the CG Triumvirate (Helvetica) widths of the internal fonts A–H (6–30 pt). A value too
 * wide for the label drops to the next smaller font. Values are put on one line and line endings are CRLF.
 */
final class FileLabelRenderer implements LabelRenderer
{
    private const int DOTS_PER_MM = 8;

    private const int SIDE_MARGIN_DOTS = 4;

    /**
     * The printer's physical print origin sits about 2 mm left of the media edge, so text centred on the label
     * width lands 2 mm left of the media centre; this shifts it back (measured on the label printer).
     */
    private const int CENTRE_OFFSET_DOTS = 16;

    /**
     * Point size of each internal font.
     */
    private const array FONT_POINTS = ['A' => 6, 'B' => 8, 'C' => 10, 'D' => 12, 'E' => 14, 'F' => 18, 'G' => 24, 'H' => 30];

    /**
     * Helvetica advance widths per 1000 em for printable ASCII (space to `~`).
     */
    private const array CHAR_WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function render(string $template, array $values): string
    {
        $lines = preg_split('/\r?\n/', $this->files->get(resource_path('label/'.$template))) ?: [];
        $labelWidth = $this->labelWidthDots($lines);

        $variables = [];
        foreach ($lines as $line) {
            if (preg_match('/^(V\d{2}),(\d+),[^,]*,j([lcr])/i', $line, $match) === 1 || preg_match('/^(V\d{2}),(\d+),/', $line, $match) === 1) {
                $variables[$match[1]] = ['length' => (int) $match[2], 'centred' => strtolower($match[3] ?? '') === 'c'];
            }
        }

        $valueOf = fn (string $variable): string => mb_substr(
            str_replace(["\r", "\n"], ' ', $values[$variable] ?? ''),
            0,
            $variables[$variable]['length'] ?? PHP_INT_MAX,
        );

        $lines = array_map(function (string $line) use ($values, $variables, $valueOf, $labelWidth): string {
            $isCentredText = preg_match('/^A([A-H]),(-?\d+),(-?\d+),(\d+),(\d+),(\d+),(0[^,]*),\^(V\d{2})$/', $line, $field) === 1
                && array_key_exists($field[8], $values)
                && ($variables[$field[8]]['centred'] ?? false)
                && $labelWidth !== null;

            if ($isCentredText) {
                return $this->centredText($field, $valueOf($field[8]), $labelWidth);
            }

            return preg_replace_callback(
                '/\^(V\d{2})/',
                fn (array $match): string => array_key_exists($match[1], $values) ? $valueOf($match[1]) : $match[0],
                $line,
            ) ?? $line;
        }, $lines);

        return implode("\r\n", $lines);
    }

    /**
     * @param  array<int, string>  $field  the matched text command: font, x, y, x_mul, y_mul, gap, rotation, variable
     */
    private function centredText(array $field, string $value, int $labelWidth): string
    {
        [, $font, , $y, $xMultiplier, $yMultiplier, $gap, $rotation] = $field;
        $available = $labelWidth - 2 * self::SIDE_MARGIN_DOTS;

        $width = $this->textWidth($value, $font, (int) $xMultiplier, (int) $gap);
        while ($width > $available && $font > 'A') {
            $font = chr(ord($font) - 1);
            $width = $this->textWidth($value, $font, (int) $xMultiplier, (int) $gap);
        }

        $x = max(0, intdiv($labelWidth - $width, 2) + self::CENTRE_OFFSET_DOTS);

        return "A{$font},{$x},{$y},{$xMultiplier},{$yMultiplier},{$gap},{$rotation},{$value}";
    }

    private function textWidth(string $value, string $font, int $xMultiplier, int $gap): int
    {
        $em = self::FONT_POINTS[$font] * self::DOTS_PER_MM * 25.4 / 72;
        $units = 0;
        foreach (str_split($value) as $character) {
            $units += self::CHAR_WIDTHS[ord($character) - 32] ?? 556;
        }

        return (int) ceil(($units / 1000 * $em + $gap * max(0, strlen($value) - 1)) * max(1, $xMultiplier));
    }

    /**
     * @param  list<string>  $lines
     */
    private function labelWidthDots(array $lines): ?int
    {
        foreach ($lines as $line) {
            if (preg_match('/^\^W(\d+)/', $line, $match) === 1) {
                return (int) $match[1] * self::DOTS_PER_MM;
            }
        }

        return null;
    }
}
