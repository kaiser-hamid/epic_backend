<?php

namespace Modules\BloodBank\Enums;

/**
 * The result of the Malarial Parasite (ICT method) screening test. `Found` counts as a reactive result.
 */
enum MalariaResult: string
{
    case NotFound = 'not_found';
    case Found = 'found';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
