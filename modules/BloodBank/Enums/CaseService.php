<?php

namespace Modules\BloodBank\Enums;

/**
 * The path a case follows, decided by its bill (one bill, one service):
 * - `transfusion`: blood components for the patient (patient sample, donors, bags, crossmatch, issue), or only
 *   services such as cross matching or a bed charge;
 * - `autologous_prp`: the patient's own blood drawn as sample tubes; the case closes when the sample is collected;
 * - `therapeutic_phlebotomy`: blood drawn and destroyed; no sample, no bag; the case closes when it is recorded.
 */
enum CaseService: string
{
    case Transfusion = 'transfusion';
    case AutologousPrp = 'autologous_prp';
    case TherapeuticPhlebotomy = 'therapeutic_phlebotomy';

    public function label(): string
    {
        return match ($this) {
            self::Transfusion => 'Transfusion',
            self::AutologousPrp => 'Autologous PRP',
            self::TherapeuticPhlebotomy => 'Therapeutic Phlebotomy',
        };
    }

    /**
     * Procedures take no donors and close inside their own step.
     */
    public function isProcedure(): bool
    {
        return $this !== self::Transfusion;
    }

    /**
     * Copies of the patient sample label printed per print (the Autologous PRP tubes).
     */
    public function sampleLabelCopies(): int
    {
        return $this === self::AutologousPrp ? 5 : 1;
    }

    /**
     * Whether the case draws a patient sample at all.
     */
    public function takesPatientSample(): bool
    {
        return $this !== self::TherapeuticPhlebotomy;
    }
}
