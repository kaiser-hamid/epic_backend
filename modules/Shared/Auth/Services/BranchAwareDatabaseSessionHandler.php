<?php

namespace Modules\Shared\Auth\Services;

use Illuminate\Session\DatabaseSessionHandler;
use Modules\Shared\Branch\Services\SessionCurrentBranch;

/**
 * The framework's database session handler, plus the session's branch on each row so a user's
 * active sessions can be listed per branch without decrypting payloads.
 */
final class BranchAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * @param  string  $data
     * @return array<string, mixed>
     */
    protected function getDefaultPayload($data)
    {
        $payload = parent::getDefaultPayload($data);

        if ($this->container?->bound('session.store')) {
            $payload['branch_id'] = $this->container->make('session.store')->get(SessionCurrentBranch::SESSION_KEY);
        }

        return $payload;
    }
}
