<?php

namespace Modules\Shared\Doctor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Doctor\Models\Doctor;

/**
 * @mixin Doctor
 */
final class DoctorResource extends JsonResource
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
            'degree' => $this->degree,
            'address' => $this->address,
            'phone' => $this->phone,
        ];
    }
}
