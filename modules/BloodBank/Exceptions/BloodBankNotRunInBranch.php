<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A Blood Bank bill was issued in a branch that does not run a blood bank (only branches listed in
 * `sh_branch_modules` with "blood-bank" do). Nothing is saved.
 */
final class BloodBankNotRunInBranch extends DomainException
{
    public function __construct()
    {
        parent::__construct('This branch does not run a blood bank, so Blood Bank tests cannot be billed here.');
    }

    public function errorCode(): string
    {
        return 'MODULE_NOT_ENABLED_FOR_BRANCH';
    }

    public function status(): int
    {
        return 422;
    }
}
