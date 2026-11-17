<?php

namespace Modules\Shared\TestCatalog\Enums;

/**
 * Decides which bill copy a department's items print on: every lab item goes on the one Lab Copy (Pathology,
 * the branch's sample collection room); each non-lab department (imaging, Blood Bank…) gets its own Non-Lab Copy
 * with its room in the branch. Unrelated to the item category, which decides billing behaviour.
 */
enum DepartmentType: string
{
    case Lab = 'lab';
    case NonLab = 'non_lab';

    public function label(): string
    {
        return match ($this) {
            self::Lab => 'Lab',
            self::NonLab => 'Non-Lab',
        };
    }
}
