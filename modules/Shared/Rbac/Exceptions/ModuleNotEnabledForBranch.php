<?php

namespace Modules\Shared\Rbac\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A permission of an optional module (e.g. Blood Bank) was sent for a branch that does not run that module.
 */
final class ModuleNotEnabledForBranch extends DomainException
{
    /**
     * @param  list<string>  $moduleLabels
     */
    public function __construct(array $moduleLabels, string $branchName)
    {
        parent::__construct(implode(', ', $moduleLabels).' is not run in '.$branchName.', so its permissions cannot be given there.');
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
