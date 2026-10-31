<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\PrintSampleLabelsData;
use Modules\BloodBank\Data\SampleView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\SampleNotInCase;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Records the print of one or more sample labels of a case ("Label" on a row, or "Label (All)") and returns
 * what goes on each label; the barcode is the sample no. The print log decides whether a label is a reprint,
 * and a reprint without a reason rejects the whole batch, so either every label is logged or none.
 */
final class PrintSampleLabels
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly PatientDirectory $patients,
    ) {}

    /**
     * @return list<SampleView> in the requested order
     *
     * @throws SampleNotInCase
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, PatientCase $case, PrintSampleLabelsData $data): array
    {
        $samples = $case->samples()->whereKey($data->sampleIds)->with('caseDonor.donor')->get()->keyBy('id');

        if ($samples->count() !== count($data->sampleIds)) {
            throw new SampleNotInCase;
        }

        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;

        return DB::transaction(fn (): array => array_map(function (int $sampleId) use ($actor, $case, $data, $samples, $patient): SampleView {
            /** @var Sample $sample */
            $sample = $samples[$sampleId]->setRelation('patientCase', $case);

            return new SampleView(
                $sample,
                new SubjectView($sample->subject_type, $sample->caseDonor, $sample->case_donor_id === null ? $patient : null),
                $this->printLogger->record(PrintDocumentType::SampleLabel, $sample, $actor, $data->reprintReason),
            );
        }, $data->sampleIds));
    }
}
