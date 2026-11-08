<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\BagView;
use Modules\BloodBank\Data\PrintBagLabelsData;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\CollectionNotInCase;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\BagLabelCommands;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Records the print of one or more bag labels of a case ("Label" on a row, or "Label (All)") and returns what
 * goes on each label with its printer command; the barcode is the bag no. The print log decides whether a label
 * is a reprint, and a reprint without a reason rejects the whole batch, so either every label is logged or none.
 */
final class PrintBagLabels
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly PatientDirectory $patients,
        private readonly BagLabelCommands $labelCommands,
    ) {}

    /**
     * @return list<BagView> in the requested order
     *
     * @throws CollectionNotInCase
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, PatientCase $case, PrintBagLabelsData $data): array
    {
        $collections = Collection::query()
            ->whereKey($data->collectionIds)
            ->whereHas('caseDonor', fn (Builder $query) => $query->where('case_id', $case->id))
            ->with(['caseDonor.donor', 'collector'])
            ->get()
            ->keyBy('id');

        if ($collections->count() !== count($data->collectionIds)) {
            throw new CollectionNotInCase;
        }

        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;

        $printed = DB::transaction(fn (): array => array_map(function (int $collectionId) use ($actor, $case, $data, $collections, $patient): BagView {
            /** @var Collection $collection */
            $collection = $collections[$collectionId];
            $collection->caseDonor->setRelation('patientCase', $case);

            return new BagView(
                $collection,
                $patient,
                $this->printLogger->record(PrintDocumentType::BagLabel, $collection, $actor, $data->reprintReason),
            );
        }, $data->collectionIds));

        $commands = $this->labelCommands->forBags($printed);

        return array_map(
            fn (BagView $view): BagView => new BagView($view->collection, $view->patient, $view->labelPrint, $commands[$view->collection->id]),
            $printed,
        );
    }
}
