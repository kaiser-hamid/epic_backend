<?php

use App\Providers\AppServiceProvider;
use Modules\BloodBank\Providers\BloodBankServiceProvider;
use Modules\Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    BloodBankServiceProvider::class,
];
