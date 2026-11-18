<?php

namespace Modules\Shared\Support\Contracts;

/**
 * Lets a module declare which of its tables hold user-entered business records (invoices, cases, units...)
 * as opposed to configuration (users, doctors, test catalog...), so `data:reset` can clear them without Shared
 * knowing any module's tables. A module tags its implementation with `TAG` in its service provider.
 */
interface TransactionalData
{
    public const string TAG = 'support.transactional_data';

    /**
     * Tables whose rows are all user-entered records. Never list configuration tables here.
     *
     * @return list<string>
     */
    public function tables(): array;

    /**
     * Puts back any counter on a kept (configuration) table that was derived from the cleared records,
     * e.g. a stock balance. Runs inside the reset's transaction.
     */
    public function afterReset(): void;
}
