<?php

namespace Modules\BloodBank\Enums;

/**
 * The donor serological investigation tests, by `bb_test_definitions.code`. Blood group is one combined result
 * (`BloodGroup` value, e.g. `A_POS`); the others are screening tests answered `reactive` / `non_reactive`.
 */
enum SerologyTest: string
{
    case BloodGroup = 'BLOOD_GROUP';
    case Hbsag = 'HBSAG';
    case AntiHcv = 'ANTI_HCV';
    case AntiHiv = 'ANTI_HIV';
    case Syphilis = 'SYPHILIS';

    /**
     * The field name of this test in the serology form and response.
     */
    public function field(): string
    {
        return strtolower($this->value);
    }

    public function isScreening(): bool
    {
        return $this !== self::BloodGroup;
    }
}
