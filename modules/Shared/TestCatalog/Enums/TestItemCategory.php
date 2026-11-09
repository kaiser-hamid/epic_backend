<?php

namespace Modules\Shared\TestCatalog\Enums;

/**
 * Which kind of bill a test item belongs to. An invoice holds items of one category only, and a category may
 * have a module that opens its own record from the bill (a Blood Bank item opens a Blood Bank case).
 * Unrelated to the department, which only groups items.
 */
enum TestItemCategory: string
{
    case General = 'general';
    case BloodBank = 'blood_bank';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::BloodBank => 'Blood Bank',
        };
    }
}
