<?php

namespace Modules\Shared\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Shared\Auth\Exceptions\PasswordChangeRequired;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks everything except `me`, password change and logout until a forced password change is done.
 */
final class EnsurePasswordIsChanged
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws PasswordChangeRequired
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            throw new PasswordChangeRequired;
        }

        return $next($request);
    }
}
