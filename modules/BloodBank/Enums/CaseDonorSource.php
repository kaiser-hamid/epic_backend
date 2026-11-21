<?php

namespace Modules\BloodBank\Enums;

/**
 * Where a case donor's blood comes from: a person who came to donate (`donor`), or a unit already in stock
 * (`stock`). A stock row stands in for a donor so the bag goes through the usual steps (sample, serology, label,
 * issue): it points to its unit (`unit_id`) and to that unit's original donor, and skips the physical exam.
 */
enum CaseDonorSource: string
{
    case Donor = 'donor';
    case Stock = 'stock';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
