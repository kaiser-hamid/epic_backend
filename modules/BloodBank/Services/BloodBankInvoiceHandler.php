<?php

namespace Modules\BloodBank\Services;

use LogicException;
use Modules\BloodBank\Actions\OpenCaseForInvoice;
use Modules\BloodBank\Data\InvoiceCaseDetailsData;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Exceptions\BloodBankNotRunInBranch;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceCategoryHandler;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Branch\Contracts\BranchModules;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Spatie\LaravelData\Data;

/**
 * Plugs Blood Bank into Shared billing: a Blood Bank bill must carry "Care of", may only be issued in a branch
 * that runs a blood bank, and saving it opens its case.
 */
final class BloodBankInvoiceHandler implements InvoiceCategoryHandler
{
    public function __construct(
        private readonly OpenCaseForInvoice $openCaseForInvoice,
        private readonly BranchModules $branchModules,
    ) {}

    public function category(): TestItemCategory
    {
        return TestItemCategory::BloodBank;
    }

    public function validateDetails(?array $details): Data
    {
        return InvoiceCaseDetailsData::validateAndCreate($details ?? []);
    }

    /**
     * @throws BloodBankNotRunInBranch rolls the bill back
     */
    public function issued(User $actor, IssuedInvoice $invoice, Data $details): void
    {
        if (! $details instanceof InvoiceCaseDetailsData) {
            throw new LogicException('Blood Bank bill details must come from validateDetails().');
        }

        if (! $this->branchModules->isEnabled($invoice->branchId, BloodBankPermission::MODULE)) {
            throw new BloodBankNotRunInBranch;
        }

        $this->openCaseForInvoice->handle($actor, $invoice, $details);
    }
}
