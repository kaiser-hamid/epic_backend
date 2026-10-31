<?php

use Illuminate\Support\Facades\Route;
use Modules\BloodBank\Http\Controllers\CaseController;
use Modules\BloodBank\Http\Controllers\ComponentTypeController;
use Modules\BloodBank\Http\Controllers\DonorController;
use Modules\BloodBank\Http\Controllers\SampleCollectionController;
use Modules\BloodBank\Http\Controllers\SampleController;

/*
| Blood Bank API under `/api/blood-bank`. Permission checks use `can:` and are evaluated in the session's branch.
*/

Route::prefix('blood-bank')->name('blood-bank.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('cases', [CaseController::class, 'index'])->middleware('can:bb.case.view')->name('cases.index');
    Route::post('cases', [CaseController::class, 'store'])->middleware('can:bb.case.create')->name('cases.store');
    Route::get('cases/{case}', [CaseController::class, 'show'])->middleware('can:view,case')->name('cases.show');

    Route::get('sample-collection', [SampleCollectionController::class, 'lookup'])->middleware('can:bb.sample.view')->name('sample-collection.lookup');
    Route::get('sample-collection/pending', [SampleCollectionController::class, 'pending'])->middleware('can:bb.sample.view')->name('sample-collection.pending');
    Route::get('cases/{case}/sample-collection', [SampleCollectionController::class, 'show'])->middleware('can:viewSamples,case')->name('cases.sample-collection.show');
    Route::post('cases/{case}/samples', [SampleCollectionController::class, 'collect'])->middleware('can:collectSamples,case')->name('cases.samples.store');
    Route::post('cases/{case}/sample-labels', [SampleCollectionController::class, 'printLabels'])->middleware('can:printSampleLabels,case')->name('cases.sample-labels.store');
    Route::get('samples', [SampleController::class, 'index'])->middleware('can:bb.sample.view')->name('samples.index');

    Route::get('donors', [DonorController::class, 'index'])->middleware('can:bb.donor.view')->name('donors.index');
    Route::get('donors/lookup', [DonorController::class, 'lookup'])->middleware('can:bb.donor.view')->name('donors.lookup');
    Route::get('donors/{donor}', [DonorController::class, 'show'])->middleware('can:bb.donor.view')->name('donors.show');

    Route::get('component-types', [ComponentTypeController::class, 'index'])->name('component-types.index');
});
