<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\CreateUserData;
use Modules\Shared\Auth\Models\User;

/**
 * Creates a staff account with no permissions. The initial password must be changed on first login.
 */
final class CreateUser
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'employee_id' => $data->employeeId,
                'name' => $data->name,
                'username' => $data->username,
                'password' => $data->password,
                'designation' => $data->designation,
                'phone' => $data->phone,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $this->auditLogger->record(AuditAction::UserCreated, $user, newValues: $user->only(['employee_id', 'name', 'username', 'designation', 'phone']));

            return $user;
        });
    }
}
