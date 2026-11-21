<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseDonorNotStockUnit extends DomainException
{
    public function __construct()
    {
        parent::__construct('This donor is not a stock unit; collect their blood instead.');
    }

    public function errorCode(): string
    {
        return 'CASE_DONOR_NOT_STOCK_UNIT';
    }

    public function status(): int
    {
        return 422;
    }
}
