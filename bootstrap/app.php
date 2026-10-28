<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Shared\Support\Exceptions\DomainException;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $error = fn (string $message, string $code, int $status, array $extra = [], array $headers = []) => response()->json(
            ['message' => $message, 'code' => $code, ...$extra],
            $status,
            $headers,
        );

        $exceptions->render(fn (DomainException $exception) => $error(
            $exception->getMessage(),
            $exception->errorCode(),
            $exception->status(),
            $exception->context(),
        ));

        $exceptions->render(fn (ValidationException $exception) => $error(
            $exception->getMessage(),
            'VALIDATION_FAILED',
            $exception->status,
            ['errors' => $exception->errors()],
        ));

        $exceptions->render(fn (InvalidQuery $exception) => $error($exception->getMessage(), 'INVALID_QUERY', $exception->getStatusCode()));

        $exceptions->render(fn (AuthenticationException $exception) => $error('Unauthenticated.', 'UNAUTHENTICATED', 401));

        // The framework has already turned authorization, CSRF and model-not-found failures into HTTP exceptions here.
        $exceptions->render(fn (AccessDeniedHttpException $exception) => $error('This action is unauthorized.', 'FORBIDDEN', 403));

        $exceptions->render(fn (ThrottleRequestsException $exception) => $error(
            'Too many attempts. Please try again later.',
            'TOO_MANY_ATTEMPTS',
            429,
            headers: $exception->getHeaders(),
        ));

        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $request->is('api/*')
            ? $error('Resource not found.', 'NOT_FOUND', 404)
            : null);

        $exceptions->render(fn (HttpException $exception, Request $request) => $request->is('api/*') && $exception->getStatusCode() === 419
            ? $error('CSRF token mismatch.', 'CSRF_TOKEN_MISMATCH', 419)
            : null);
    })->create();
