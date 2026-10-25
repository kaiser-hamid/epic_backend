<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\ConsumableBatchFactory;

#[Table('bb_consumable_batches')]
#[Fillable(['consumable_id', 'batch_no', 'expires_at', 'quantity_on_hand'])]
#[UseFactory(ConsumableBatchFactory::class)]
final class ConsumableBatch extends Model
{
    /** @use HasFactory<ConsumableBatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'quantity_on_hand' => 'decimal:3',
        ];
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class, 'consumable_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class, 'batch_id');
    }
}
