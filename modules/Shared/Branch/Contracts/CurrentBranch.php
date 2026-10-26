<?php

namespace Modules\Shared\Branch\Contracts;

use Modules\Shared\Branch\Models\Branch;

/**
 * The branch chosen at login. A session works in exactly one branch; switching means logging in again.
 * Modules use it to stamp `branch_id` on new records and to scope their queries.
 */
interface CurrentBranch
{
    public function id(): ?int;

    public function get(): ?Branch;
}
