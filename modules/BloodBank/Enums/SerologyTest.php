<?php

namespace Modules\BloodBank\Enums;

/**
 * The donor serological investigation tests, by `bb_test_definitions.code`. Blood group is one combined result
 * (`BloodGroup` value, e.g. `A_POS`); the screening tests are answered `reactive` / `non_reactive`, except the
 * malarial parasite test, which is `found` / `not_found`.
 */
enum SerologyTest: string
{
    case BloodGroup = 'BLOOD_GROUP';
    case Hbsag = 'HBSAG';
    case AntiHcv = 'ANTI_HCV';
    case AntiHiv = 'ANTI_HIV';
    case Syphilis = 'SYPHILIS';
    case MalarialParasite = 'MALARIAL_PARASITE';

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

    /**
     * A stored screening result as printed ("Non-Reactive", "Negative"); an unknown value is returned as is.
     */
    public function resultLabel(string $result): string
    {
        $label = match ($this) {
            self::MalarialParasite => MalariaResult::tryFrom($result)?->label(),
            self::BloodGroup => null,
            default => ScreeningResult::tryFrom($result)?->label(),
        };

        return $label ?? $result;
    }
}
