<?php

namespace Modules\BloodBank\Enums;

/**
 * Which crossmatch a unit gets, by its component: whole blood both (major and minor), PCV the major (patient
 * serum against the donor's cells) and every plasma or platelet component (FFP, FP, cryo, cryo poor plasma,
 * platelets, PRP, apheresis) the minor. It is never chosen on screen: "Issue Blood" records the unit compatible
 * on the sides its scope covers, and the Compatibility Test Report prints it ("Compatible (Major)").
 */
enum CrossmatchScope: string
{
    case Major = 'major';
    case Minor = 'minor';
    case MajorMinor = 'major_minor';

    public static function forComponent(string $componentCode): self
    {
        return match ($componentCode) {
            'WB' => self::MajorMinor,
            'PCV' => self::Major,
            default => self::Minor,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Major => 'Major',
            self::Minor => 'Minor',
            self::MajorMinor => 'Major/Minor',
        };
    }

    public function coversMajor(): bool
    {
        return $this !== self::Minor;
    }

    public function coversMinor(): bool
    {
        return $this !== self::Major;
    }
}
