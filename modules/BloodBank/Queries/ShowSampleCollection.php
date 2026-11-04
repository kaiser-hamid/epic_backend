<?php

namespace Modules\BloodBank\Queries;

use Modules\BloodBank\Data\SampleCollectionLookupData;
use Modules\BloodBank\Data\SampleCollectionView;
use Modules\BloodBank\Data\SampleView;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\CaseSampleSubjects;
use Modules\BloodBank\Services\SampleLabelCommands;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * The sample collection screen after an invoice scan: the session branch's case billed by that invoice, its
 * patient and invoice, and one row per subject with its latest sample and label print count.
 */
final class ShowSampleCollection
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly InvoiceDirectory $invoices,
        private readonly PatientDirectory $patients,
        private readonly CaseSampleSubjects $sampleSubjects,
        private readonly PrintLogger $printLogger,
        private readonly SampleLabelCommands $labelCommands,
    ) {}

    /**
     * @throws CaseNotFound
     */
    public function find(SampleCollectionLookupData $data): SampleCollectionView
    {
        return $this->get($this->caseFor($data));
    }

    /**
     * @param  list<int>  $collectedSampleIds  samples just drawn by CollectSamples; their label printer commands are included
     */
    public function get(PatientCase $case, array $collectedSampleIds = []): SampleCollectionView
    {
        $case->load('components.componentType');
        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;
        $subjects = $this->sampleSubjects->of($case, $patient);

        $labelPrintCounts = [];
        $collected = [];
        foreach ($subjects as $subject) {
            if ($subject->sample !== null) {
                $labelPrintCounts[$subject->sample->id] = $this->printLogger->printCount(PrintDocumentType::SampleLabel, $subject->sample);

                if (in_array($subject->sample->id, $collectedSampleIds, true)) {
                    $collected[] = new SampleView($subject->sample, $subject->subject);
                }
            }
        }

        return new SampleCollectionView(
            $case,
            $patient,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
            $subjects,
            $labelPrintCounts,
            $collectedSampleIds,
            $this->labelCommands->for($case, $collected),
        );
    }

    /**
     * @throws CaseNotFound
     */
    private function caseFor(SampleCollectionLookupData $data): PatientCase
    {
        // Once the case no replaces the invoice no:
        // return PatientCase::query()
        //     ->where('branch_id', $this->currentBranch->id())
        //     ->where('case_no', $data->caseNo)
        //     ->first() ?? throw new CaseNotFound;

        $billingId = $this->invoices->idForInvoiceNo($data->invoiceNo) ?? throw new CaseNotFound;

        return PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->billedBy($billingId)
            ->latest('id')
            ->first() ?? throw new CaseNotFound;
    }
}
