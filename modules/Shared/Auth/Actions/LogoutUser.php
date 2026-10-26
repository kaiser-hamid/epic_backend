<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Models\User;

final class LogoutUser
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Request $request,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $user): void
    {
        $this->auditLogger->record(AuditAction::Logout, $user, actor: $user);

        $this->auth->guard('web')->logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }
}
