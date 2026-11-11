<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\CreateUserData;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\EmployeeId;

/**
 * Creates a staff account with no permissions and the next employee id. The initial password must be
 * changed on first login.
 */
final class CreateUser
{
    public function __construct(
        private readonly EmployeeId $employeeId,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'employee_id' => $this->employeeId->next(),
                'name' => $data->name,
                'username' => $data->username,
                'password' => $data->password,
                'designation' => $data->designation,
                'phone' => $data->phone,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $this->auditLogger->record(AuditAction::UserCreated, $user, newValues: [
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'username' => $user->username,
                'designation' => $user->designation?->value,
                'phone' => $user->phone,
            ]);

            return $user;
        });
    }
}
