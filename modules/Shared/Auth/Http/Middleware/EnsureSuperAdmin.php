<?php

namespace Modules\Shared\Auth\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_super_admin) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
