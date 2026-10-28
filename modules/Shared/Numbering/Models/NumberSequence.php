<?php

namespace Modules\Shared\Numbering\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The last value issued for one sequence in one scope. Setting `last_value` continues a vendor's running number.
 */
#[Table('sh_number_sequences')]
#[Fillable(['sequence', 'scope', 'last_value'])]
final class NumberSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
