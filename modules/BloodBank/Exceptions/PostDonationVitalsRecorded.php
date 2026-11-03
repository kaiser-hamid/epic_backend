<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PostDonationVitalsRecorded extends DomainException
{
    public function __construct()
    {
        parent::__construct('The post-donation vitals of this donor are already recorded.');
    }

    public function errorCode(): string
    {
        return 'POST_DONATION_VITALS_RECORDED';
    }

    public function status(): int
    {
        return 409;
    }
}
