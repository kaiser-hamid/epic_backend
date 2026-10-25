<?php

namespace Modules\BloodBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\SampleFactory;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;

#[Table('bb_samples')]
#[Fillable(['sample_no', 'case_id', 'case_donor_id', 'subject_type', 'collected_at', 'collected_by', 'status'])]
#[UseFactory(SampleFactory::class)]
final class Sample extends Model
{
    /** @use HasFactory<SampleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => SampleSubject::class,
            'status' => SampleStatus::class,
            'collected_at' => 'datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function caseDonor(): BelongsTo
    {
        return $this->belongsTo(CaseDonor::class, 'case_donor_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestResult::class, 'sample_id');
    }
}
