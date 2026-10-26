<?php

namespace Modules\Shared\Branch\Services;

use Illuminate\Http\Request;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Branch\Models\Branch;

final class SessionCurrentBranch implements CurrentBranch
{
    public const SESSION_KEY = 'branch_id';

    public function __construct(private readonly Request $request) {}

    public function id(): ?int
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        $branchId = $this->request->session()->get(self::SESSION_KEY);

        return $branchId === null ? null : (int) $branchId;
    }

    public function get(): ?Branch
    {
        $branchId = $this->id();

        return $branchId === null ? null : Branch::query()->find($branchId);
    }
}
