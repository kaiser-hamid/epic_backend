<?php

namespace Modules\BloodBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\CrossmatchFactory;
use Modules\BloodBank\Enums\CrossmatchResult;
use Modules\BloodBank\Enums\TestResultStatus;

/**
 * Compatibility test of a patient sample against one unit (bag), whether the unit came from the case's own
 * donor or from stock. Donor details and screening come from the unit's collection. Versioned like test results.
 */
#[Table('bb_crossmatches')]
#[Fillable(['case_id', 'patient_sample_id', 'unit_id', 'version', 'is_current', 'major_result', 'minor_result', 'remarks', 'tested_by', 'tested_at', 'status', 'reviewed_by', 'reviewed_at', 'amendment_reason'])]
#[UseFactory(CrossmatchFactory::class)]
final class Crossmatch extends Model
{
    /** @use HasFactory<CrossmatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'major_result' => CrossmatchResult::class,
            'minor_result' => CrossmatchResult::class,
            'status' => TestResultStatus::class,
            'tested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isCompatible(): bool
    {
        return $this->major_result === CrossmatchResult::Compatible
            && $this->minor_result === CrossmatchResult::Compatible;
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function patientSample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'patient_sample_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
