<?php

use Illuminate\Support\Facades\Route;
use Modules\BloodBank\Http\Controllers\CaseController;
use Modules\BloodBank\Http\Controllers\DonorController;

/*
| Blood Bank API under `/api/blood-bank`. Permission checks use `can:` and are evaluated in the session's branch.
*/

Route::prefix('blood-bank')->name('blood-bank.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('cases', [CaseController::class, 'index'])->middleware('can:bb.case.view')->name('cases.index');
    Route::post('cases', [CaseController::class, 'store'])->middleware('can:bb.case.create')->name('cases.store');
    Route::get('cases/{case}', [CaseController::class, 'show'])->middleware('can:view,case')->name('cases.show');

    Route::get('donors', [DonorController::class, 'index'])->middleware('can:bb.donor.view')->name('donors.index');
});
