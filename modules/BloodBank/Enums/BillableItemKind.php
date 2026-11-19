<?php

namespace Modules\BloodBank\Enums;

/**
 * What a billed Blood Bank catalog item means to Blood Bank (`bb_billable_items`). Items without a row (cross
 * matching, bed charges, infusion…) are plain services and change nothing in the case.
 */
enum BillableItemKind: string
{
    /** Requests units of a component type (`component_type_id` × `units_per_quantity` per billed quantity). */
    case Component = 'component';

    /** Sets the case's service to a procedure (`service`). */
    case Procedure = 'procedure';

    /** The patient's own units are kept in storage and issued on a later visit (Stock WB/PRBC/FFP). */
    case StorageFee = 'storage_fee';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
