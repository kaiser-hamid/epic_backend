<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\SampleView;

/**
 * A sample with its case and subject; also the content of a printed label (the barcode is `sample_no`).
 * `label_print` is present only in the response to a label print.
 *
 * @property-read SampleView $resource
 */
final class SampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sample = $this->resource->sample;
        $labelPrint = $this->resource->labelPrint;

        return [
            'id' => $sample->id,
            'sample_no' => $sample->sample_no,
            'status' => $sample->status->value,
            'collected_at' => $sample->collected_at->toIso8601String(),
            'collected_by' => $this->when($sample->relationLoaded('collector'), fn () => [
                'id' => $sample->collector->id,
                'name' => $sample->collector->name,
            ]),
            'case' => $this->when($sample->relationLoaded('patientCase'), fn () => [
                'id' => $sample->patientCase->id,
                'case_no' => $sample->patientCase->case_no,
                'care_type' => $sample->patientCase->care_type->value,
            ]),
            'subject' => SubjectResource::make($this->resource->subject),
            'label_print' => $this->when($labelPrint !== null, fn () => [
                'is_reprint' => $labelPrint->is_reprint,
                'reprint_reason' => $labelPrint->reprint_reason,
                'printed_at' => $labelPrint->printed_at->toIso8601String(),
            ]),
        ];
    }
}
