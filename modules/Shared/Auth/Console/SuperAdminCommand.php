<?php

namespace Modules\Shared\Auth\Console;

use Illuminate\Console\Command;
use Modules\Shared\Auth\Actions\SetSuperAdmin;
use Modules\Shared\Auth\Exceptions\LastSuperAdmin;
use Modules\Shared\Auth\Models\User;

/**
 * The only way to grant or revoke super admin, so it stays with whoever has server access (IT/Developer).
 */
final class SuperAdminCommand extends Command
{
    protected $signature = 'auth:super-admin {username : The account to change} {--revoke : Remove super admin instead of granting it}';

    protected $description = 'Grant or revoke super admin for an IT/Developer account';

    public function handle(SetSuperAdmin $setSuperAdmin): int
    {
        $user = User::query()->where('username', $this->argument('username'))->first();

        if ($user === null) {
            $this->error('No user with that username.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        try {
            $setSuperAdmin->handle($user, $grant);
        } catch (LastSuperAdmin $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($grant ? "{$user->username} is now a super admin." : "{$user->username} is no longer a super admin.");

        return self::SUCCESS;
    }
}
