<?php

namespace Modules\Shared\Auth\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Branch\Models\Branch;

/**
 * One row from the database session store, with its branch attached by ListUserSessions.
 *
 * @property object{id: string, branch: Branch|null, ip_address: string|null, user_agent: string|null, last_activity: int} $resource
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = $this->resource->branch;

        return [
            'id' => $this->resource->id,
            'branch' => $branch === null ? null : ['id' => $branch->id, 'code' => $branch->code, 'name' => $branch->name],
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'last_activity_at' => CarbonImmutable::createFromTimestamp($this->resource->last_activity, config('app.timezone'))->toIso8601String(),
            'is_current' => $request->hasSession() && $request->session()->getId() === $this->resource->id,
        ];
    }
}
