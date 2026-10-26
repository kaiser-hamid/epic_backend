<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\CollectionFactory;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\Shared\Auth\Models\User;

#[Table('bb_collections')]
#[Fillable(['bag_no', 'case_donor_id', 'volume_ml', 'collected_at', 'collected_by', 'approved_by', 'status', 'notes'])]
#[UseFactory(CollectionFactory::class)]
final class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
            'collected_at' => 'datetime',
        ];
    }

    public function caseDonor(): BelongsTo
    {
        return $this->belongsTo(CaseDonor::class, 'case_donor_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'collection_id');
    }
}
