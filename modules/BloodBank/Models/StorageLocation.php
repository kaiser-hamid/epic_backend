<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\StorageLocationFactory;
use Modules\BloodBank\Enums\StorageLocationType;

#[Table('bb_storage_locations')]
#[Fillable(['code', 'name', 'type', 'min_temperature_c', 'max_temperature_c', 'is_active'])]
#[UseFactory(StorageLocationFactory::class)]
final class StorageLocation extends Model
{
    /** @use HasFactory<StorageLocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StorageLocationType::class,
            'min_temperature_c' => 'decimal:2',
            'max_temperature_c' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'storage_location_id');
    }
}
