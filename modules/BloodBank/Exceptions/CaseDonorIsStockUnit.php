<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseDonorIsStockUnit extends DomainException
{
    public function __construct()
    {
        parent::__construct('This row is a stock unit: it has no bag to collect; print its patient label instead.');
    }

    public function errorCode(): string
    {
        return 'CASE_DONOR_IS_STOCK_UNIT';
    }

    public function status(): int
    {
        return 422;
    }
}
