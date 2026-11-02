<?php

namespace Modules\Shared\Billing\Contracts;

use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\AdditionalInvoiceData;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Billing\Exceptions\InvoiceNoTaken;

/**
 * Lets other modules bill a patient from their own forms (e.g. Blood Bank case registration): registers the
 * patient when `patient` is given instead of `patient_id`, then bills the items in the session's branch.
 * It joins the caller's transaction, so a failure later in the caller rolls the patient, the bill and their
 * numbers back too. Callers keep only the returned ids and numbers, never Shared models.
 */
interface InvoiceIssuer
{
    /**
     * @throws BranchInactive
     */
    public function issue(User $actor, CreateInvoiceData $data): IssuedInvoice;

    /**
     * Bills the source bill's patient again, with the same referral, for one catalog item, under an invoice
     * number issued elsewhere (e.g. an extra donor's charge on the vendor system).
     *
     * @throws BranchInactive
     * @throws InvoiceNoTaken
     */
    public function issueAdditional(User $actor, AdditionalInvoiceData $data): IssuedInvoice;
}
