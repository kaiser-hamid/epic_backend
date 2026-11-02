<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;

/**
 * The component preparation screen for one bag: the bag (with donor and patient), its case (with `components`
 * loaded), the units already made from it, and what the case already holds. Rendered by
 * ComponentPreparationResource.
 */
final readonly class ComponentPreparationView
{
    /**
     * @param  list<UnitView>  $units
     * @param  array<int, int>  $labelPrintCounts  keyed by unit id
     * @param  array<int, int>  $heldCounts  units reserved for or issued to the case, keyed by component type id
     * @param  list<int>  $preparedUnitIds  units made by the request that produced this view
     */
    public function __construct(
        public BagView $bag,
        public PatientCase $case,
        public array $units,
        public array $labelPrintCounts,
        public array $heldCounts,
        public bool $canPrepare,
        public array $preparedUnitIds = [],
    ) {}
}
