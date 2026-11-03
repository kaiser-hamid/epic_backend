<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PostDonationVitalsRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('Record the post-donation vitals of every donor whose blood is issued.');
    }

    public function errorCode(): string
    {
        return 'POST_DONATION_VITALS_REQUIRED';
    }
}
