<?php

namespace Modules\BloodBank\Services;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Support\Contracts\TransactionalData;

/**
 * Blood Bank's user-entered tables. Kept as configuration: test definitions, component types, storage
 * locations and the consumables list itself (only its stock batches and movements are cleared).
 */
final class BloodBankTransactionalData implements TransactionalData
{
    public function tables(): array
    {
        return [
            'bb_unit_movements',
            'bb_crossmatches',
            'bb_case_components',
            'bb_test_results',
            'bb_samples',
            'bb_donor_screenings',
            'bb_case_donors',
            'bb_units',
            'bb_collections',
            'bb_cases',
            'bb_donors',
            'bb_consumable_transactions',
            'bb_consumable_batches',
        ];
    }

    /**
     * Stock movements are gone, so the consumables' running balance goes back to zero.
     */
    public function afterReset(): void
    {
        DB::table('bb_consumables')->update(['current_balance' => 0]);
    }
}
