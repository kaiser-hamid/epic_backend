<?php

namespace Modules\Shared\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Services\EloquentAuditLogger;
use Modules\Shared\Auth\Console\SuperAdminCommand;
use Modules\Shared\Auth\Http\Middleware\EnsureBranchSession;
use Modules\Shared\Auth\Http\Middleware\EnsurePasswordIsChanged;
use Modules\Shared\Auth\Http\Middleware\EnsureSuperAdmin;
use Modules\Shared\Auth\Http\Middleware\EnsureUserIsActive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\BranchAwareDatabaseSessionHandler;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Services\SessionCurrentBranch;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Services\EloquentPrintLogger;
use Modules\Shared\Rbac\Console\SyncPermissionsCommand;
use Modules\Shared\Rbac\Contracts\AccessChecker;
use Modules\Shared\Rbac\Enums\SharedPermission;
use Modules\Shared\Rbac\Models\Role;
use Modules\Shared\Rbac\Services\BranchAccessChecker;
use Modules\Shared\Rbac\Services\PermissionRegistry;

final class SharedServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PrintLogger::class => EloquentPrintLogger::class,
        AuditLogger::class => EloquentAuditLogger::class,
        CurrentBranch::class => SessionCurrentBranch::class,
        AccessChecker::class => BranchAccessChecker::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        PermissionRegistry::class => PermissionRegistry::class,
    ];

    public function boot(PermissionRegistry $permissions, Router $router): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../Doctor/Database/Migrations',
            __DIR__.'/../TestCatalog/Database/Migrations',
            __DIR__.'/../Patient/Database/Migrations',
            __DIR__.'/../Billing/Database/Migrations',
            __DIR__.'/../Printing/Database/Migrations',
            __DIR__.'/../Branch/Database/Migrations',
            __DIR__.'/../Auth/Database/Migrations',
            __DIR__.'/../Rbac/Database/Migrations',
            __DIR__.'/../Audit/Database/Migrations',
        ]);

        Relation::morphMap([
            'sh.user' => User::class,
            'sh.branch' => Branch::class,
            'sh.role' => Role::class,
        ]);

        $permissions->register(SharedPermission::class);

        $this->bootAuthorization();
        $this->bootAuthentication($router);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class, SuperAdminCommand::class]);
        }
    }

    /**
     * Any ability that is a catalog permission (e.g. `can:bb.unit.issue`) is answered by the branch-scoped
     * AccessChecker; other abilities fall through to Policies as usual.
     */
    private function bootAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $this->app->make(PermissionRegistry::class)->has($ability)) {
                return null;
            }

            return $this->app->make(AccessChecker::class)->allows($user, $ability);
        });
    }

    private function bootAuthentication(Router $router): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        $this->app->make(SessionManager::class)->extend('database', function ($app): BranchAwareDatabaseSessionHandler {
            return new BranchAwareDatabaseSessionHandler(
                $app['db']->connection($app['config']['session.connection']),
                $app['config']['session.table'],
                $app['config']['session.lifetime'],
                $app,
            );
        });

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by(Str::lower($request->string('username')->toString()).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip|'.$request->ip()),
        ]);

        RateLimiter::for('login-branches', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        $router->aliasMiddleware('erp.active', EnsureUserIsActive::class);
        $router->aliasMiddleware('erp.branch', EnsureBranchSession::class);
        $router->aliasMiddleware('erp.password-changed', EnsurePasswordIsChanged::class);
        $router->aliasMiddleware('erp.super-admin', EnsureSuperAdmin::class);
        $router->middlewareGroup('erp.auth', ['auth:sanctum', 'erp.active', 'erp.branch']);

        Route::prefix('api')->middleware('api')->group(__DIR__.'/../Routes/api.php');
    }
}
