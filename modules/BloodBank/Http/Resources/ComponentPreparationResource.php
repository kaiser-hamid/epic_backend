<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\ComponentPreparationView;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Models\CaseComponent;

/**
 * The component preparation screen: `bag` (bag, donor, case and patient, as on the bag label), `case` with the
 * billed `components`, each with `held_count` (units already reserved for or issued to the case) and
 * `needed_count` (what this bag must give the patient, see PrepareUnits), and `units`
 * made from the bag with `label_print_count` (0 = never printed, so the next print needs no reason). "Prepare" is
 * enabled while `can_prepare`. After a prepare, `prepared_unit_ids` lists the new units so their labels can be
 * printed straight away.
 *
 * @property-read ComponentPreparationView $resource
 */
final class ComponentPreparationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->resource->case;

        return [
            'bag' => BagResource::make($this->resource->bag)->toArray($request),
            'case' => [
                ...CaseSummaryResource::make($case)->toArray($request),
                'components' => $case->components->sortBy('id')->map(function (CaseComponent $component) use ($request): array {
                    $held = $this->resource->heldCounts[$component->component_type_id] ?? 0;

                    return [
                        ...CaseComponentResource::make($component)->toArray($request),
                        'held_count' => $held,
                        'needed_count' => max(0, $component->quantity - $held),
                    ];
                })->values()->all(),
            ],
            'units' => array_map(fn (UnitView $unit): array => [
                ...UnitResource::make($unit)->toArray($request),
                'label_print_count' => $this->resource->labelPrintCounts[$unit->unit->id] ?? 0,
            ], $this->resource->units),
            'can_prepare' => $this->resource->canPrepare,
            'prepared_unit_ids' => $this->resource->preparedUnitIds,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->resource->preparedUnitIds !== []) {
            $response->setStatusCode(201);
        }
    }
}
