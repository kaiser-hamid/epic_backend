<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Shared\Auth\Http\Controllers\LoginBranchController;
use Modules\Shared\Auth\Http\Controllers\PasswordController;
use Modules\Shared\Auth\Http\Controllers\SessionController;
use Modules\Shared\Auth\Http\Controllers\UserAccountController;
use Modules\Shared\Auth\Http\Controllers\UserController;
use Modules\Shared\Billing\Http\Controllers\InvoiceController;
use Modules\Shared\Branch\Http\Controllers\BranchController;
use Modules\Shared\Patient\Http\Controllers\PatientController;
use Modules\Shared\Rbac\Http\Controllers\PermissionController;
use Modules\Shared\Rbac\Http\Controllers\RoleController;
use Modules\Shared\Rbac\Http\Controllers\UserBranchPermissionController;

/*
| `erp.auth` = signed in + active account + active branch session. `erp.password-changed` additionally
| blocks accounts that still have to replace an admin-set password. Permission checks use `can:` and are
| evaluated in the session's branch.
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('login-branches', LoginBranchController::class)->middleware('throttle:login-branches')->name('login-branches');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login');

    Route::middleware('erp.auth')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('me', [AuthenticatedSessionController::class, 'show'])->name('me');
        Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password');

        Route::middleware('erp.password-changed')->group(function (): void {
            Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('sessions', [SessionController::class, 'destroyOthers'])->name('sessions.destroy-others');
            Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        });
    });
});

Route::prefix('access')->name('access.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->middleware('can:sh.user.view')->name('users.index');
    Route::post('users', [UserController::class, 'store'])->middleware('can:sh.user.create')->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:sh.user.view')->name('users.show');
    Route::patch('users/{user}', [UserController::class, 'update'])->middleware('can:sh.user.update')->name('users.update');

    Route::post('users/{user}/activate', [UserAccountController::class, 'activate'])->middleware('can:sh.user.deactivate')->name('users.activate');
    Route::post('users/{user}/deactivate', [UserAccountController::class, 'deactivate'])->middleware('can:sh.user.deactivate')->name('users.deactivate');
    Route::post('users/{user}/reset-password', [UserAccountController::class, 'resetPassword'])->middleware('can:sh.user.reset_password')->name('users.reset-password');
    Route::post('users/{user}/unlock', [UserAccountController::class, 'unlock'])->middleware('can:sh.user.unlock')->name('users.unlock');
    Route::post('users/{user}/logout', [UserAccountController::class, 'forceLogout'])->middleware('can:sh.user.force_logout')->name('users.logout');

    Route::get('users/{user}/branch-permissions', [UserBranchPermissionController::class, 'index'])->middleware('can:sh.user.view')->name('users.branch-permissions.index');
    Route::put('users/{user}/branches/{branch}/permissions', [UserBranchPermissionController::class, 'update'])->middleware('can:sh.user.assign_permissions')->name('users.branch-permissions.update');

    Route::get('permissions', PermissionController::class)->middleware('can:sh.role.view')->name('permissions.index');

    Route::get('roles', [RoleController::class, 'index'])->middleware('can:sh.role.view')->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('can:sh.role.view')->name('roles.show');
    Route::post('roles', [RoleController::class, 'store'])->middleware('can:sh.role.manage')->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:sh.role.manage')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:sh.role.manage')->name('roles.destroy');

    Route::middleware('erp.super-admin')->group(function (): void {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    });
});

Route::prefix('patients')->name('patients.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('/', [PatientController::class, 'index'])->middleware('can:sh.patient.view')->name('index');
    Route::post('/', [PatientController::class, 'store'])->middleware('can:sh.patient.create')->name('store');
    Route::get('{patient}', [PatientController::class, 'show'])->middleware('can:sh.patient.view')->name('show');
});

Route::prefix('billing')->name('billing.')->middleware(['erp.auth', 'erp.password-changed'])->group(function (): void {
    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:sh.billing.view')->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->middleware('can:sh.billing.create')->name('invoices.store');
    Route::get('invoices/{billing}', [InvoiceController::class, 'show'])->middleware('can:view,billing')->name('invoices.show');
});
