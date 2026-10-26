<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\BloodBank\Database\Factories\CaseDonorFactory;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\Shared\Auth\Models\User;

#[Table('bb_case_donors')]
#[Fillable(['case_id', 'donor_id', 'billing_id', 'sequence_no', 'status', 'deferral_reason', 'assessed_by', 'assessed_at'])]
#[UseFactory(CaseDonorFactory::class)]
final class CaseDonor extends Model
{
    /** @use HasFactory<CaseDonorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CaseDonorStatus::class,
            'assessed_at' => 'datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class, 'donor_id');
    }

    public function screening(): HasOne
    {
        return $this->hasOne(DonorScreening::class, 'case_donor_id');
    }

    /**
     * The physician who accepted or deferred the donor.
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'case_donor_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class, 'case_donor_id');
    }
}
