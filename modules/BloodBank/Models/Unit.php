<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\UnitFactory;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\Shared\Patient\Enums\BloodGroup;

#[Table('bb_units')]
/**
 * One component bag (WB, PCV, FFP…), filled straight from the donor at collection: there is no primary bag and no
 * later preparation. Its `unit_no` is the number on its label.
 */
#[Fillable(['branch_id', 'unit_no', 'collection_id', 'component_type_id', 'blood_group', 'volume_ml', 'status', 'storage_location_id', 'case_id', 'expires_at'])]
#[UseFactory(UnitFactory::class)]
final class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blood_group' => BloodGroup::class,
            'status' => UnitStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }

    /**
     * The case this unit is reserved for or issued to.
     */
    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'component_type_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function crossmatches(): HasMany
    {
        return $this->hasMany(Crossmatch::class, 'unit_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(UnitMovement::class, 'unit_id');
    }
}
