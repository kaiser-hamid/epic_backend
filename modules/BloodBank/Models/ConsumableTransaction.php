<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\ConsumableTransactionFactory;
use Modules\BloodBank\Enums\ConsumableTransactionType;
use Modules\Shared\Auth\Models\User;

#[Table('bb_consumable_transactions')]
#[Fillable(['consumable_id', 'batch_id', 'type', 'quantity', 'balance_after', 'reason', 'performed_by', 'occurred_at'])]
#[UseFactory(ConsumableTransactionFactory::class)]
final class ConsumableTransaction extends Model
{
    /** @use HasFactory<ConsumableTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConsumableTransactionType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class, 'consumable_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ConsumableBatch::class, 'batch_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
