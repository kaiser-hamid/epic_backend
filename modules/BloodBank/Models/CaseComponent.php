<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\CaseComponentFactory;

/**
 * A requested component for a case (e.g. 2 × PCV). Fulfilled by units of the same component type allocated to the case.
 */
#[Table('bb_case_components')]
#[Fillable(['case_id', 'component_type_id', 'quantity'])]
#[UseFactory(CaseComponentFactory::class)]
final class CaseComponent extends Model
{
    /** @use HasFactory<CaseComponentFactory> */
    use HasFactory;

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'component_type_id');
    }
}
