<?php

namespace Modules\Shared\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Billing\Database\Factories\DiscountPolicyFactory;
use Modules\Shared\Billing\Enums\DiscountType;

/**
 * A discount rule a bill may apply ("General 25%"). `rate_bp` is in basis points (2500 = 25 %). A bill keeps a
 * snapshot of the policy it used, so changing a policy never alters an issued bill.
 */
#[Table('sh_discount_policies')]
#[Fillable(['code', 'name', 'type', 'rate_bp', 'is_active'])]
#[UseFactory(DiscountPolicyFactory::class)]
final class DiscountPolicy extends Model
{
    /** @use HasFactory<DiscountPolicyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function patientTypes(): BelongsToMany
    {
        return $this->belongsToMany(PatientType::class, 'sh_patient_type_discount_policies')->withTimestamps();
    }
}
