<?php

namespace Modules\Shared\Branch\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Branch\Models\Branch;

/**
 * @mixin Branch
 */
final class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->whenHas('address'),
            'phone' => $this->whenHas('phone'),
            'is_active' => $this->whenHas('is_active'),
        ];
    }
}
