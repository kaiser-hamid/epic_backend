<?php

namespace Modules\BloodBank\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\Unit;

final class BloodBankServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Relation::morphMap([
            'bb.case' => PatientCase::class,
            'bb.sample' => Sample::class,
            'bb.collection' => Collection::class,
            'bb.unit' => Unit::class,
            'bb.crossmatch' => Crossmatch::class,
        ]);
    }
}
