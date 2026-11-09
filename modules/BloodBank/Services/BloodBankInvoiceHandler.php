<?php

namespace Modules\BloodBank\Services;

use LogicException;
use Modules\BloodBank\Actions\OpenCaseForInvoice;
use Modules\BloodBank\Data\InvoiceCaseDetailsData;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceCategoryHandler;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Spatie\LaravelData\Data;

/**
 * Plugs Blood Bank into Shared billing: a Blood Bank bill must carry "Care of", and saving it opens its case.
 */
final class BloodBankInvoiceHandler implements InvoiceCategoryHandler
{
    public function __construct(private readonly OpenCaseForInvoice $openCaseForInvoice) {}

    public function category(): TestItemCategory
    {
        return TestItemCategory::BloodBank;
    }

    public function validateDetails(?array $details): Data
    {
        return InvoiceCaseDetailsData::validateAndCreate($details ?? []);
    }

    public function issued(User $actor, IssuedInvoice $invoice, Data $details): void
    {
        if (! $details instanceof InvoiceCaseDetailsData) {
            throw new LogicException('Blood Bank bill details must come from validateDetails().');
        }

        $this->openCaseForInvoice->handle($actor, $invoice, $details);
    }
}
