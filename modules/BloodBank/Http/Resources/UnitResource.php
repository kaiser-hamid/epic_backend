<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\UnitView;

/**
 * A blood unit with its component, storage location and source bag; also the content of a component label (the
 * barcode is `unit_no`). `reserved_for` is the case and patient the unit is reserved for or issued to, null for
 * stock. `screening` (the donor's screening results), `label_print` and `label_command` (the barcode printer
 * command to send to the print service) are present only in the response to a label print.
 *
 * @property-read UnitView $resource
 */
final class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->resource->unit;
        $collection = $unit->collection;
        $patient = $this->resource->patient;
        $labelPrint = $this->resource->labelPrint;

        return [
            'id' => $unit->id,
            'unit_no' => $unit->unit_no,
            'status' => $unit->status->value,
            'component' => [
                'id' => $unit->componentType->id,
                'code' => $unit->componentType->code,
                'name' => $unit->componentType->name,
                'storage_condition' => $unit->componentType->storage_condition,
            ],
            'blood_group' => $unit->blood_group?->value,
            'volume_ml' => $unit->volume_ml,
            'storage_location' => $unit->storageLocation === null ? null : [
                'id' => $unit->storageLocation->id,
                'code' => $unit->storageLocation->code,
                'name' => $unit->storageLocation->name,
            ],
            'bag_no' => $collection->bag_no,
            'donor_no' => $collection->caseDonor->donor->donor_no,
            'collected_at' => $collection->collected_at->toIso8601String(),
            'prepared_at' => $unit->prepared_at->toIso8601String(),
            'expires_at' => $unit->expires_at->toIso8601String(),
            'reserved_for' => $unit->patientCase === null ? null : [
                'case_id' => $unit->patientCase->id,
                'case_no' => $unit->patientCase->case_no,
                'patient_no' => $patient?->patientNo,
                'patient_name' => $patient?->name,
            ],
            'screening' => $this->when($this->resource->screening !== null, fn () => $this->resource->screening),
            'label_print' => $this->when($labelPrint !== null, fn () => [
                'is_reprint' => $labelPrint->is_reprint,
                'reprint_reason' => $labelPrint->reprint_reason,
                'printed_at' => $labelPrint->printed_at->toIso8601String(),
            ]),
            'label_command' => $this->when($labelPrint !== null, $this->resource->labelCommand),
        ];
    }
}
