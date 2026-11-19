<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BloodBank\Database\Factories\PatientCaseFactory;
use Modules\BloodBank\Enums\CareType;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Policies\PatientCasePolicy;
use Modules\Shared\Auth\Models\User;

#[Table('bb_cases')]
#[Fillable([
    'case_no', 'patient_master_id', 'billing_id', 'branch_id', 'care_type', 'service', 'keeps_units_in_storage', 'transfusion_reason',
    'reference_note', 'status', 'supplied_at', 'closed_at', 'closed_by', 'close_reason', 'created_by',
])]
#[UseFactory(PatientCaseFactory::class)]
#[UsePolicy(PatientCasePolicy::class)]
final class PatientCase extends Model
{
    /** @use HasFactory<PatientCaseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'service' => 'transfusion',
        'keeps_units_in_storage' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'care_type' => CareType::class,
            'service' => CaseService::class,
            'keeps_units_in_storage' => 'boolean',
            'status' => CaseStatus::class,
            'supplied_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function phlebotomy(): HasOne
    {
        return $this->hasOne(Phlebotomy::class, 'case_id');
    }

    public function caseDonors(): HasMany
    {
        return $this->hasMany(CaseDonor::class, 'case_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class, 'case_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(CaseComponent::class, 'case_id');
    }

    public function crossmatches(): HasMany
    {
        return $this->hasMany(Crossmatch::class, 'case_id');
    }

    /**
     * Units reserved for or issued to this case.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'case_id');
    }

    /**
     * Cases billed by this invoice: the case bill or a replacement donor's bill.
     */
    #[Scope]
    protected function billedBy(Builder $query, int $billingId): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('billing_id', $billingId)
            ->orWhereHas('caseDonors', fn (Builder $query) => $query->where('billing_id', $billingId)));
    }
}
