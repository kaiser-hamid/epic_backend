<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\ChangePasswordData;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\SessionManager;

/**
 * Changes the signed-in user's own password, clears a forced change, and signs out their other sessions.
 */
final class ChangePassword
{
    public function __construct(
        private readonly Request $request,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, ChangePasswordData $data): void
    {
        if (mb_strtolower($data->password) === mb_strtolower($user->username)) {
            throw ValidationException::withMessages(['password' => 'The password must not be the same as your username.']);
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data->password,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            $this->sessions->revokeAllExcept($user, $this->request->session()->getId());

            $this->auditLogger->record(AuditAction::PasswordChanged, $user, actor: $user);
        });
    }
}
