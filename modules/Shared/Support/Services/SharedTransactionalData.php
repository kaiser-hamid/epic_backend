<?php

namespace Modules\Shared\Support\Services;

use Modules\Shared\Support\Contracts\TransactionalData;

/**
 * Shared's user-entered tables. Number sequences are included so invoice and case numbers start again from 1.
 * The audit log is not here: it is append-only and only cleared when `data:reset` is told to.
 */
final class SharedTransactionalData implements TransactionalData
{
    public function tables(): array
    {
        return [
            'sh_print_logs',
            'sh_billing_discounts',
            'sh_billing_referrals',
            'sh_billing_items',
            'sh_billing',
            'sh_patient_master',
            'sh_number_sequences',
        ];
    }

    public function afterReset(): void {}
}
