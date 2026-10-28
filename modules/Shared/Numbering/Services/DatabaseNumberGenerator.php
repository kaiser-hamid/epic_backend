<?php

namespace Modules\Shared\Numbering\Services;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Numbering\Contracts\NumberGenerator;
use Modules\Shared\Numbering\Models\NumberSequence;

/**
 * Locks the sequence row so concurrent counters never receive the same value.
 */
final class DatabaseNumberGenerator implements NumberGenerator
{
    public function next(string $sequence, string $scope = 'global'): int
    {
        return DB::transaction(function () use ($sequence, $scope): int {
            NumberSequence::query()->insertOrIgnore([
                'sequence' => $sequence,
                'scope' => $scope,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = NumberSequence::query()
                ->where('sequence', $sequence)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->sole();

            $row->increment('last_value');

            return $row->last_value;
        });
    }
}
