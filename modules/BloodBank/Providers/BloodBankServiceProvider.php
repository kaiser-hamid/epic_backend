<?php

namespace Modules\BloodBank\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Rbac\Services\PermissionRegistry;

final class BloodBankServiceProvider extends ServiceProvider
{
    public function boot(PermissionRegistry $permissions): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Relation::morphMap([
            'bb.case' => PatientCase::class,
            'bb.donor' => Donor::class,
            'bb.sample' => Sample::class,
            'bb.collection' => Collection::class,
            'bb.unit' => Unit::class,
            'bb.crossmatch' => Crossmatch::class,
        ]);

        $permissions->register(BloodBankPermission::class);

        Route::prefix('api')->middleware('api')->group(__DIR__.'/../Routes/api.php');
    }
}
