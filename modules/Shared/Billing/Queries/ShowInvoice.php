<?php

namespace Modules\Shared\Billing\Queries;

use Modules\Shared\Billing\Models\Billing;

final class ShowInvoice
{
    public function get(Billing $billing): Billing
    {
        return $billing->load([
            'patient',
            'items.testItem',
            'referral.doctor',
            'referral.secondDoctor',
            'referral.referredByStaff',
            'creator',
        ]);
    }
}
