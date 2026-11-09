<?php

use Illuminate\Support\Facades\Route;
use Modules\BloodBank\Http\Controllers\BloodCollectionController;
use Modules\BloodBank\Http\Controllers\BloodIssueController;
use Modules\BloodBank\Http\Controllers\CaseController;
use Modules\BloodBank\Http\Controllers\ComponentPreparationController;
use Modules\BloodBank\Http\Controllers\ComponentTypeController;
use Modules\BloodBank\Http\Controllers\DonorController;
use Modules\BloodBank\Http\Controllers\DonorExaminationController;
use Modules\BloodBank\Http\Controllers\DonorSerologyController;
use Modules\BloodBank\Http\Controllers\SampleCollectionController;
use Modules\BloodBank\Http\Controllers\SampleController;
use Modules\BloodBank\Http\Controllers\StorageLocationController;

/*
| Blood Bank API under `/api/blood-bank`. Permission checks use `can:` and are evaluated in the session's branch.
*/

Route::prefix('blood-bank')->name('blood-bank.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('cases', [CaseController::class, 'index'])->middleware('can:bb.case.view')->name('cases.index');
    Route::get('cases/{case}', [CaseController::class, 'show'])->middleware('can:view,case')->name('cases.show');
    Route::patch('cases/{case}', [CaseController::class, 'update'])->middleware('can:update,case')->name('cases.update');
    Route::get('add-donor', [CaseController::class, 'lookup'])->middleware('can:bb.case.update')->name('add-donor.lookup');
    Route::post('cases/{case}/donors', [CaseController::class, 'addDonor'])->middleware('can:addDonor,case')->name('cases.donors.store');

    Route::get('sample-collection', [SampleCollectionController::class, 'lookup'])->middleware('can:bb.sample.view')->name('sample-collection.lookup');
    Route::get('sample-collection/pending', [SampleCollectionController::class, 'pending'])->middleware('can:bb.sample.view')->name('sample-collection.pending');
    Route::get('cases/{case}/sample-collection', [SampleCollectionController::class, 'show'])->middleware('can:viewSamples,case')->name('cases.sample-collection.show');
    Route::post('cases/{case}/samples', [SampleCollectionController::class, 'collect'])->middleware('can:collectSamples,case')->name('cases.samples.store');
    Route::post('cases/{case}/sample-labels', [SampleCollectionController::class, 'printLabels'])->middleware('can:printSampleLabels,case')->name('cases.sample-labels.store');
    Route::get('samples', [SampleController::class, 'index'])->middleware('can:bb.sample.view')->name('samples.index');

    Route::get('donor-examination', [DonorExaminationController::class, 'lookup'])->middleware('can:bb.donor_exam.view')->name('donor-examination.lookup');
    Route::get('donor-examination/pending', [DonorExaminationController::class, 'pending'])->middleware('can:bb.donor_exam.view')->name('donor-examination.pending');
    Route::get('cases/{case}/donor-examination', [DonorExaminationController::class, 'show'])->middleware('can:viewDonorExamination,case')->name('cases.donor-examination.show');
    Route::put('cases/{case}/donors/{caseDonor}/physical-examination', [DonorExaminationController::class, 'record'])->middleware('can:examineDonor,case')->name('cases.donors.physical-examination.update');

    Route::get('donor-serology', [DonorSerologyController::class, 'lookup'])->middleware('can:bb.serology.view')->name('donor-serology.lookup');
    Route::get('donor-serology/pending', [DonorSerologyController::class, 'pending'])->middleware('can:bb.serology.view')->name('donor-serology.pending');
    Route::get('cases/{case}/donor-serology', [DonorSerologyController::class, 'show'])->middleware('can:viewDonorSerology,case')->name('cases.donor-serology.show');
    Route::put('cases/{case}/donors/{caseDonor}/serology', [DonorSerologyController::class, 'record'])->middleware('can:recordDonorSerology,case')->name('cases.donors.serology.update');

    Route::get('blood-collection', [BloodCollectionController::class, 'lookup'])->middleware('can:bb.collection.view')->name('blood-collection.lookup');
    Route::get('blood-collection/pending', [BloodCollectionController::class, 'pending'])->middleware('can:bb.collection.view')->name('blood-collection.pending');
    Route::get('cases/{case}/blood-collection', [BloodCollectionController::class, 'show'])->middleware('can:viewBloodCollection,case')->name('cases.blood-collection.show');
    Route::post('cases/{case}/donors/{caseDonor}/collection', [BloodCollectionController::class, 'collect'])->middleware('can:collectBlood,case')->name('cases.donors.collection.store');
    Route::post('cases/{case}/bag-labels', [BloodCollectionController::class, 'printLabels'])->middleware('can:printBagLabels,case')->name('cases.bag-labels.store');
    Route::get('blood-collections', [BloodCollectionController::class, 'index'])->middleware('can:bb.collection.view')->name('blood-collections.index');

    Route::get('component-preparation', [ComponentPreparationController::class, 'lookup'])->middleware('can:bb.unit.view')->name('component-preparation.lookup');
    Route::get('component-preparation/pending', [ComponentPreparationController::class, 'pending'])->middleware('can:bb.unit.view')->name('component-preparation.pending');
    Route::get('cases/{case}/bags/{collection}/component-preparation', [ComponentPreparationController::class, 'show'])->middleware('can:viewUnits,case')->name('cases.bags.component-preparation.show');
    Route::post('cases/{case}/bags/{collection}/units', [ComponentPreparationController::class, 'prepare'])->middleware('can:prepareUnits,case')->name('cases.bags.units.store');
    Route::post('cases/{case}/unit-labels', [ComponentPreparationController::class, 'printLabels'])->middleware('can:printUnitLabels,case')->name('cases.unit-labels.store');
    Route::get('units', [ComponentPreparationController::class, 'index'])->middleware('can:bb.unit.view')->name('units.index');

    Route::get('blood-issue', [BloodIssueController::class, 'lookup'])->middleware('can:bb.issue.view')->name('blood-issue.lookup');
    Route::get('blood-issue/pending', [BloodIssueController::class, 'pending'])->middleware('can:bb.issue.view')->name('blood-issue.pending');
    Route::get('cases/{case}/blood-issue', [BloodIssueController::class, 'show'])->middleware('can:viewBloodIssue,case')->name('cases.blood-issue.show');
    Route::post('cases/{case}/blood-issue', [BloodIssueController::class, 'issue'])->middleware('can:issueBlood,case')->name('cases.blood-issue.store');
    Route::post('cases/{case}/compatibility-reports', [BloodIssueController::class, 'printReports'])->middleware('can:printCompatibilityReports,case')->name('cases.compatibility-reports.store');

    Route::get('donors', [DonorController::class, 'index'])->middleware('can:bb.donor.view')->name('donors.index');
    Route::get('donors/lookup', [DonorController::class, 'lookup'])->middleware('can:bb.donor.view')->name('donors.lookup');
    Route::get('donors/{donor}', [DonorController::class, 'show'])->middleware('can:bb.donor.view')->name('donors.show');

    Route::get('component-types', [ComponentTypeController::class, 'index'])->name('component-types.index');
    Route::get('storage-locations', [StorageLocationController::class, 'index'])->name('storage-locations.index');
});
