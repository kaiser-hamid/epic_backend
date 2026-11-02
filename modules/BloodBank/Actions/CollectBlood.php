<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\CollectBloodData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Exceptions\BagAlreadyCollected;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotEligible;
use Modules\BloodBank\Exceptions\DonorNotFit;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowBloodCollection;
use Modules\BloodBank\Services\BagNumber;
use Modules\BloodBank\Services\BloodCollectionGate;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Collects blood from one eligible donor into a bag with its own bag no, and marks the case donor `collected`.
 * The case donor row is locked so a double click cannot draw two bags. The bag label is printed separately
 * (PrintBagLabels), straight after.
 */
final class CollectBlood
{
    public function __construct(
        private readonly BloodCollectionGate $collectionGate,
        private readonly BagNumber $bagNumber,
        private readonly AuditLogger $auditLogger,
        private readonly ShowBloodCollection $showBloodCollection,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws DonorNotFit
     * @throws DonorNotEligible
     * @throws DonorDeferred
     * @throws BagAlreadyCollected
     */
    public function handle(User $actor, PatientCase $case, CaseDonor $caseDonor, CollectBloodData $data): BloodCollectionView
    {
        [$case, $collection] = DB::transaction(function () use ($actor, $case, $caseDonor, $data): array {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($caseDonor->case_id !== $case->id) {
                throw new CaseDonorNotInCase;
            }

            $caseDonor = CaseDonor::query()->with('donor')->lockForUpdate()->findOrFail($caseDonor->id);

            if (($refusal = $this->collectionGate->refusal($caseDonor)) !== null) {
                throw $refusal;
            }

            $collection = Collection::query()->create([
                'bag_no' => $this->bagNumber->next(),
                'case_donor_id' => $caseDonor->id,
                'volume_ml' => $data->volumeMl,
                'collected_at' => $data->collectedAt ?? now(),
                'collected_by' => $actor->id,
                'status' => CollectionStatus::Collected,
                'notes' => $data->notes,
            ]);

            $caseDonor->update(['status' => CaseDonorStatus::Collected]);

            $this->auditLogger->record(BloodBankAuditAction::BloodCollected, $collection, newValues: [
                ...$collection->only(['bag_no', 'case_donor_id', 'volume_ml', 'collected_at', 'notes']),
                'case_no' => $case->case_no,
                'donor_no' => $caseDonor->donor->donor_no,
            ]);

            return [$case->setRelations([]), $collection];
        });

        return $this->showBloodCollection->get($case, collectedCollectionId: $collection->id);
    }
}
