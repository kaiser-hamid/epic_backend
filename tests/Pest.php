<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Models\BranchModule;
use Modules\Shared\Branch\Services\SessionCurrentBranch;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;
use Modules\Shared\Rbac\Models\Permission;
use Modules\Shared\Rbac\Models\UserBranchPermission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/Tests/Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Grant permissions to a user in one branch, syncing the permission catalog first. A permission of an
 * optional module (e.g. Blood Bank) also switches that module on in the branch, as an admin must first.
 */
function grantInBranch(User $user, Branch $branch, BackedEnum|string ...$permissions): void
{
    app(SyncPermissionCatalog::class)->handle();

    foreach ($permissions as $permission) {
        $entry = Permission::where('name', $permission instanceof BackedEnum ? $permission->value : $permission)->firstOrFail();

        if ($entry->module_is_optional) {
            enableModulesIn($branch, $entry->module);
        }

        UserBranchPermission::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'permission_id' => $entry->id,
        ]);
    }
}

/**
 * Switch optional modules (e.g. "blood-bank") on in a branch.
 */
function enableModulesIn(Branch $branch, string ...$modules): void
{
    foreach ($modules as $module) {
        BranchModule::query()->firstOrCreate(['branch_id' => $branch->id, 'module' => $module]);
    }
}

/**
 * Act as a signed-in user whose session works in the given branch, the way the Next.js frontend calls the API.
 */
function signInToBranch(TestCase $test, User $user, Branch $branch): TestCase
{
    return $test->actingAs($user)
        ->withHeader('Referer', 'http://localhost')
        ->withSession([SessionCurrentBranch::SESSION_KEY => $branch->id]);
}
