<?php

namespace Modules\Shared\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Auth\Models\User;

/**
 * A staff member as shown in a picker: no account or security details.
 *
 * @mixin User
 */
final class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'designation' => $this->designation,
        ];
    }
}
