<?php

namespace Modules\Shared\Auth\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who was deactivated while signed in.
 */
final class EnsureUserIsActive
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (! $user->is_active || $user->trashed())) {
            $this->auth->guard('web')->logout();
            $request->session()->invalidate();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
