<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BloodBank\Database\Factories\ConsumableFactory;

#[Table('bb_consumables')]
#[Fillable(['code', 'name', 'unit_of_measure', 'minimum_stock', 'current_balance', 'tracks_batch', 'tracks_expiry', 'is_active'])]
#[UseFactory(ConsumableFactory::class)]
final class Consumable extends Model
{
    /** @use HasFactory<ConsumableFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:3',
            'current_balance' => 'decimal:3',
            'tracks_batch' => 'boolean',
            'tracks_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ConsumableBatch::class, 'consumable_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class, 'consumable_id');
    }
}
