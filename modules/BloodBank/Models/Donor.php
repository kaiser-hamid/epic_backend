<?php

namespace Modules\BloodBank\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BloodBank\Database\Factories\DonorFactory;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;

#[Table('bb_donors')]
#[Fillable(['donor_no', 'name', 'date_of_birth', 'age', 'gender', 'blood_group', 'phone', 'address', 'national_id', 'is_regular_volunteer', 'created_by'])]
#[UseFactory(DonorFactory::class)]
final class Donor extends Model
{
    /** @use HasFactory<DonorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'is_regular_volunteer' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function caseDonors(): HasMany
    {
        return $this->hasMany(CaseDonor::class, 'donor_id');
    }

    /**
     * The age in whole years as of today, from the date of birth. A donor registered with only an age is aged
     * forward from the registration date, so a returning donor's age never goes stale.
     */
    public function currentAge(): ?int
    {
        $today = CarbonImmutable::today();

        if ($this->date_of_birth !== null) {
            return (int) $this->date_of_birth->toImmutable()->startOfDay()->diffInYears($today);
        }

        if ($this->age === null || $this->created_at === null) {
            return $this->age;
        }

        return $this->age + (int) $this->created_at->toImmutable()->startOfDay()->diffInYears($today);
    }
}
