<?php

namespace Modules\Shared\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Billing\Database\Factories\PatientTypeFactory;

/**
 * Chosen on every bill ("General Patient", …); groups the discount policies the bill may use.
 */
#[Table('sh_patient_types')]
#[Fillable(['code', 'name', 'is_active'])]
#[UseFactory(PatientTypeFactory::class)]
final class PatientType extends Model
{
    /** @use HasFactory<PatientTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The type every bill defaults to, inserted by the migration.
     */
    public const string GENERAL = 'GENERAL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function discountPolicies(): BelongsToMany
    {
        return $this->belongsToMany(DiscountPolicy::class, 'sh_patient_type_discount_policies')->withTimestamps();
    }
}
