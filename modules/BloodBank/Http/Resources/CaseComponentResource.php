<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Models\CaseComponent;

/**
 * @mixin CaseComponent
 */
final class CaseComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'component_type_id' => $this->component_type_id,
            'code' => $this->whenLoaded('componentType', fn () => $this->componentType->code),
            'name' => $this->whenLoaded('componentType', fn () => $this->componentType->name),
            'quantity' => $this->quantity,
        ];
    }
}
