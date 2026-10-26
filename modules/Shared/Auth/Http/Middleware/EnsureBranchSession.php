<?php

namespace Modules\Shared\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in request must belong to an active branch chosen at login. If the branch is gone or
 * deactivated, the session ends and the user must log in again.
 */
final class EnsureBranchSession
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly CurrentBranch $currentBranch,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws BranchInactive
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $this->currentBranch->get();

        if ($branch === null || ! $branch->is_active) {
            $this->auth->guard('web')->logout();
            $request->session()->invalidate();

            throw new BranchInactive;
        }

        return $next($request);
    }
}
