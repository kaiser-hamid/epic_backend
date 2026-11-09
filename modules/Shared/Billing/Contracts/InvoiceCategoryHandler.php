<?php

namespace Modules\Shared\Billing\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Spatie\LaravelData\Data;

/**
 * Lets the module that owns a bill category open its own record from a saved bill (a Blood Bank bill opens a
 * Blood Bank case), so Shared billing never depends on that module. A module tags its implementation with
 * `TAG` in its service provider; at most one handler per category.
 *
 * Both methods run inside the bill's transaction: anything thrown rolls the bill back.
 */
interface InvoiceCategoryHandler
{
    public const string TAG = 'billing.invoice_category_handlers';

    public function category(): TestItemCategory;

    /**
     * Validates the category's own fields sent with the bill (`category_details`), before anything is saved.
     * Error keys are relative to `category_details`.
     *
     * @param  array<string, mixed>|null  $details
     *
     * @throws ValidationException
     */
    public function validateDetails(?array $details): Data;

    /**
     * Called once, right after the bill is saved, with what `validateDetails()` returned.
     */
    public function issued(User $actor, IssuedInvoice $invoice, Data $details): void;
}
