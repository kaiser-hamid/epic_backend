<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Services\EloquentPrintLogger;

final class SharedServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PrintLogger::class => EloquentPrintLogger::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../Doctor/Database/Migrations',
            __DIR__.'/../TestCatalog/Database/Migrations',
            __DIR__.'/../Patient/Database/Migrations',
            __DIR__.'/../Billing/Database/Migrations',
            __DIR__.'/../Printing/Database/Migrations',
        ]);
    }
}
