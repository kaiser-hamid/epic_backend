<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\PrintUnitLabelsData;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Exceptions\UnitNotInCase;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\DonorSerologyGate;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Records the print of one or more component labels of units made from this case's bags and returns what goes
 * on each label: the barcode is the unit no, with the donor's screening results and, for a reserved unit, the
 * patient it is for. A reprint without a reason rejects the whole batch, so either every label is logged or none.
 */
final class PrintUnitLabels
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly PatientDirectory $patients,
        private readonly DonorSerologyGate $serologyGate,
    ) {}

    /**
     * @return list<UnitView> in the requested order
     *
     * @throws UnitNotInCase
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, PatientCase $case, PrintUnitLabelsData $data): array
    {
        $units = Unit::query()
            ->whereKey($data->unitIds)
            ->whereHas('collection.caseDonor', fn (Builder $query) => $query->where('case_id', $case->id))
            ->with(['componentType', 'storageLocation', 'collection.caseDonor.donor', 'collection.caseDonor.samples.results.testDefinition', 'patientCase'])
            ->get()
            ->keyBy('id');

        if ($units->count() !== count($data->unitIds)) {
            throw new UnitNotInCase;
        }

        $patients = $this->patients->summaries($units->pluck('patientCase.patient_master_id')->filter()->unique()->values()->all());

        return DB::transaction(fn (): array => array_map(function (int $unitId) use ($actor, $data, $units, $patients): UnitView {
            /** @var Unit $unit */
            $unit = $units[$unitId];

            return new UnitView(
                $unit,
                $unit->patientCase === null ? null : ($patients[$unit->patientCase->patient_master_id] ?? null),
                $this->screening($unit->collection->caseDonor),
                $this->printLogger->record(PrintDocumentType::ComponentLabel, $unit, $actor, $data->reprintReason),
            );
        }, $data->unitIds));
    }

    /**
     * The donor's current screening results, in the serology form's order.
     *
     * @return list<array{code: string, name: string, result: string|null}>
     */
    private function screening(CaseDonor $caseDonor): array
    {
        $results = $this->serologyGate->sampleFor($caseDonor)?->results
            ->where('is_current', true)
            ->keyBy(fn (TestResult $result): string => $result->testDefinition->code);

        $screening = [];

        foreach (SerologyTest::cases() as $test) {
            if (! $test->isScreening()) {
                continue;
            }

            $result = $results?->get($test->value);

            $screening[] = [
                'code' => $test->value,
                'name' => $result?->testDefinition->name ?? $test->value,
                'result' => $result?->result_value,
            ];
        }

        return $screening;
    }
}
